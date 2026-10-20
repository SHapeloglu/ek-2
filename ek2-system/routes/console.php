<?php
use App\Models\AuditLog;
use App\Models\Ek2Record;
use App\Support\Konum;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Tamamlanmış ve OCR'a aktarılmamış kayıtları OCR giriş klasörüne yazar. Fotoğraflar kopyalanmaz: sunucudaki tek
// belge klasörüne sembolik bağ yazılır. Her bağ/dosya önce .tmp adıyla yazılıp rename edilir; eksik sayfalı kayıt beklemede kalır; künye JSON'u en son yazılır, OCR tarafı JSON'u "kayıt hazır" işareti sayabilir.
// İptal edilen kayıt: klasördeki fotoğraf + künye silinir, yerine <uuid>.iptal.json bırakılır (OCR işlediyse geri alması için).
Artisan::command('ek2:ocr-aktar {--limit=200} {--tekrar-dene : hata durumundaki kayıtları da dene}', function () {
    $hedef = rtrim(config('ek2.ocr_export_path'), '/');
    File::ensureDirectoryExists($hedef);
    $durumlar = $this->option('tekrar-dene') ? ['bekliyor','hata'] : ['bekliyor'];
    $kayitlar = Ek2Record::with(['workplace','doctor'])->whereIn('status',['completed','cancelled'])->whereIn('ocr_status',$durumlar)
        ->where(fn($q)=>$q->where('status','cancelled')->orWhere(fn($q)=>$q->whereNotNull('front_path')->whereNotNull('back_path')))
        ->orderBy('id')->limit((int) $this->option('limit'))->get();
    $disk = Storage::disk('local'); $ok = 0; $hata = 0; $hostDizin = config('ek2.belge_host_dizini');
    foreach ($kayitlar as $r) {
        try {
            if ($r->status === 'cancelled') {
                $vardi = false;
                foreach (["{$r->uuid}1.jpeg","{$r->uuid}2.jpeg","{$r->uuid}.json"] as $ad) if (is_link("{$hedef}/{$ad}") || is_file("{$hedef}/{$ad}")) { unlink("{$hedef}/{$ad}"); $vardi = true; }
                if ($vardi || $r->ocr_exported_at) {
                    $iptal = json_encode(['kayit_uuid'=>$r->uuid,'iptal_zamani'=>$r->cancelled_at?->toIso8601String(),'neden'=>$r->cancel_reason], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
                    if (file_put_contents("{$hedef}/.{$r->uuid}.iptal.json.tmp", $iptal) === false || !rename("{$hedef}/.{$r->uuid}.iptal.json.tmp", "{$hedef}/{$r->uuid}.iptal.json")) throw new RuntimeException('iptal işareti yazılamadı');
                }
                $r->update(['ocr_status'=>'aktarildi','ocr_exported_at'=>now(),'ocr_error'=>null]);
                AuditLog::kaydet('ocr_iptal_aktar',$r); $ok++; continue;
            }
            if (is_file("{$hedef}/{$r->uuid}.iptal.json")) unlink("{$hedef}/{$r->uuid}.iptal.json"); // aktife alınmış kayıt
            $sayfalar = [];
            foreach (['1'=>$r->front_path,'2'=>$r->back_path] as $no=>$yol) {
                if (!$yol || !$disk->exists($yol)) throw new RuntimeException("sayfa {$no} dosyası bulunamadı");
                $ad = "{$r->uuid}{$no}.jpeg";
                if (!str_starts_with($yol, 'ek2/')) throw new RuntimeException("sayfa {$no} belge klasörü dışında");
                @unlink("{$hedef}/.{$ad}.tmp");
                if (!symlink($hostDizin.'/'.substr($yol, 4), "{$hedef}/.{$ad}.tmp") || !rename("{$hedef}/.{$ad}.tmp", "{$hedef}/{$ad}")) throw new RuntimeException("sayfa {$no} bağı yazılamadı");
                $sayfalar[$no] = $ad;
            }
            $kunye = [
                'kayit_uuid'=>$r->uuid, 'kaynak'=>$r->source, 'eski_form_id'=>$r->legacy_form_id, 'sayfa1'=>$sayfalar['1'], 'sayfa2'=>$sayfalar['2'],
                'tc_kimlik_no'=>$r->tc_kimlik_no, 'ad'=>$r->first_name, 'soyad'=>$r->last_name,
                'isyeri'=>['id'=>$r->workplace->id, 'ad'=>$r->workplace->name, 'sgk_sicil_no'=>$r->workplace->sgk_sicil_no, 'adres'=>$r->workplace->address],
                'doktor'=>['id'=>$r->doctor->id, 'ad_soyad'=>trim($r->doctor->name.' '.$r->doctor->surname)],
                'muayene_tarihi'=>$r->exam_date?->toDateString(),
                'yukleme_zamani'=>$r->completed_at?->toIso8601String(),
                'cihaz'=>['marka'=>$r->device_make,'model'=>$r->device_model],
                'konum'=>$r->latitude===null ? null : ['enlem'=>$r->latitude,'boylam'=>$r->longitude,'kaynak'=>$r->location_source,'dogruluk_m'=>$r->location_accuracy_m,'adres'=>$r->address],
            ];
            $json = json_encode($kunye, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
            if (file_put_contents("{$hedef}/.{$r->uuid}.json.tmp", $json) === false || !rename("{$hedef}/.{$r->uuid}.json.tmp", "{$hedef}/{$r->uuid}.json")) throw new RuntimeException('künye yazılamadı');
            $r->update(['ocr_status'=>'aktarildi','ocr_exported_at'=>now(),'ocr_error'=>null]); AuditLog::kaydet('ocr_aktar',$r); $ok++;
        } catch (Throwable $e) {
            $r->update(['ocr_status'=>'hata','ocr_error'=>Str::limit($e->getMessage(), 490)]); $hata++;
            $this->error("{$r->uuid}: {$e->getMessage()}");
        }
    }
    $this->info("OCR aktarımı: {$ok} kayıt aktarıldı, {$hata} hata. Hedef: {$hedef}");
    return $hata ? 1 : 0;
})->purpose('Tamamlanmış EK-2 kayıtlarını OCR giriş klasörüne aktarır');

// Konumu olmayan kayıtlarda fotoğraf EXIF'inden GPS okur; adresi çözülmemiş kayıtları Nominatim ile adrese çevirir
// (Nominatim kuralı: saniyede en fazla 1 istek). Adres yeni çözülen ve OCR'a gitmiş kayıt yeniden aktarılır.
Artisan::command('ek2:konum {--limit=50}', function () {
    $disk = Storage::disk('local'); $exif = 0; $adres = 0; $cihaz = 0;
    // Telefonla yüklenmiş, cihaz bilgisi boş kayıtlar: EXIF marka / model (taranmış formlarda tarayıcı bilgisi olacağından atlanır)
    foreach (Ek2Record::where('source','telefon')->whereNull('device_model')->whereNull('device_make')->orderBy('id')->limit((int) $this->option('limit'))->get() as $r) {
        foreach ([$r->front_path,$r->back_path] as $p) if ($p && $disk->exists($p) && ($c = Konum::cihaz($disk->path($p)))) { $r->update($c); $cihaz++; break; }
    }
    foreach (Ek2Record::whereNull('latitude')->where('status','!=','draft')->orderBy('id')->limit((int) $this->option('limit'))->get() as $r) {
        $k = ($r->front_path && $disk->exists($r->front_path) ? Konum::exif($disk->path($r->front_path)) : null) ?? ($r->back_path && $disk->exists($r->back_path) ? Konum::exif($disk->path($r->back_path)) : null);
        if ($k) { $r->update(['latitude'=>$k['lat'],'longitude'=>$k['lon'],'location_source'=>'exif']); $exif++; }
    }
    foreach (Ek2Record::whereNotNull('latitude')->whereNull('address')->orderBy('id')->limit((int) $this->option('limit'))->get() as $i => $r) {
        if ($i) usleep(1100000);
        if ($a = Konum::adres($r->latitude, $r->longitude)) { $r->update(['address'=>$a,'address_at'=>now(),'ocr_status'=>$r->ocr_status==='aktarildi'?'bekliyor':$r->ocr_status]); $adres++; }
    }
    $this->info("Konum: {$exif} kayda EXIF'ten konum, {$adres} kayda adres, {$cihaz} kayda cihaz bilgisi eklendi.");
})->purpose('EK-2 kayıtlarına fotoğraf GPS konumu ve adres ekler');

// OCR projesinin taranmış formlarını kayıt olarak ekler. Dosyalar önceden sunucuda belge klasörüne taşınmış olmalı
// (manifest: [{form_id, uuid, tc, ad, soyad, front, back, review_note}], front/back = 'ek2/...' yolu ya da null).
// İki sayfası olan form zaten OCR'da → ocr_status 'aktarildi'; eksik sayfalı form sayfası tamamlanınca aktarılır.
Artisan::command('ek2:tarama-ice-aktar {manifest} {--doktor=} {--isyeri=} {--deneme : yalnız kontrol et, yazma}', function () {
    $doktor = App\Models\User::where('role','doctor')->findOrFail($this->option('doktor')); $isyeri = App\Models\Workplace::findOrFail($this->option('isyeri'));
    $liste = json_decode(file_get_contents($this->argument('manifest')), true, flags: JSON_THROW_ON_ERROR); $disk = Storage::disk('local'); $yeni = 0; $var = 0;
    foreach ($liste as $f) {
        foreach (['front','back'] as $s) if ($f[$s] !== null && !$disk->exists($f[$s])) throw new RuntimeException("{$f['form_id']}: {$s} dosyası yok ({$f[$s]})");
        if (Ek2Record::where('legacy_form_id',$f['form_id'])->exists()) { $var++; continue; }
        if ($this->option('deneme')) { $yeni++; continue; }
        $tam = $f['front'] && $f['back'];
        $r = Ek2Record::create(['uuid'=>$f['uuid'],'doctor_id'=>$doktor->id,'workplace_id'=>$isyeri->id,'source'=>'tarama','legacy_form_id'=>$f['form_id'],
            'tc_kimlik_no'=>$f['tc'],'first_name'=>$f['ad'],'last_name'=>$f['soyad'],'front_path'=>$f['front'],'back_path'=>$f['back'],'review_note'=>$f['review_note'],'retake_pages'=>($f['retake'] ?? []) ?: null,'retake_note'=>($f['retake'] ?? []) ? ($f['retake_note'] ?? null) : null,
            'status'=>'completed','completed_at'=>now(),'ocr_status'=>$tam?'aktarildi':'bekliyor','ocr_exported_at'=>$tam?now():null]);
        AuditLog::kaydet('kayit_ice_aktar',$r,['form_id'=>$f['form_id']]); $yeni++;
    }
    $this->info(($this->option('deneme')?'[deneme] ':'')."İçe aktarım: {$yeni} yeni kayıt, {$var} zaten vardı.");
})->purpose('OCR projesinin taranmış EK-2 formlarını kayıt olarak ekler');

// Muayene (onay) tarihini dışarıdan alır: [{"form_id" ya da "uuid", "tarih": "YYYY-MM-DD"}]. Taranmışlarda OCR etiketinden,
// ileride OCR sonucundan. Elle girilmiş tarihin üzerine yazmaz.
Artisan::command('ek2:muayene-tarihi-al {dosya} {--kaynak=ocr}', function () {
    $kaynak = $this->option('kaynak'); abort_unless(in_array($kaynak,['etiket','ocr'],true),1,'kaynak: etiket | ocr');
    $yeni = 0; $atla = 0;
    foreach (json_decode(file_get_contents($this->argument('dosya')), true, flags: JSON_THROW_ON_ERROR) as $x) {
        $r = isset($x['uuid']) ? Ek2Record::where('uuid',$x['uuid'])->first() : Ek2Record::where('legacy_form_id',$x['form_id'] ?? '')->first();
        if (!$r || $r->exam_date_source === 'elle' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($x['tarih'] ?? ''))) { $atla++; continue; }
        $r->update(['exam_date'=>$x['tarih'],'exam_date_source'=>$kaynak]); $yeni++;
    }
    $p = Ek2Record::mukerrerleriPasifeAl();
    $this->info("Muayene tarihi: {$yeni} kayda yazıldı, {$atla} atlandı. Mükerrer olarak pasife düşen: {$p}.");
})->purpose('Muayene tarihini OCR etiketinden / sonucundan alır');

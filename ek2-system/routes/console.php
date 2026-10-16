<?php
use App\Models\AuditLog;
use App\Models\Ek2Record;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Tamamlanmış ve OCR'a aktarılmamış kayıtları OCR giriş klasörüne yazar. Her dosya önce .tmp adıyla yazılıp
// rename edilir; künye JSON'u en son yazılır, OCR tarafı JSON'u "kayıt hazır" işareti sayabilir.
// İptal edilen kayıt: klasördeki fotoğraf + künye silinir, yerine <uuid>.iptal.json bırakılır (OCR işlediyse geri alması için).
Artisan::command('ek2:ocr-aktar {--limit=200} {--tekrar-dene : hata durumundaki kayıtları da dene}', function () {
    $hedef = rtrim(config('ek2.ocr_export_path'), '/');
    File::ensureDirectoryExists($hedef);
    $durumlar = $this->option('tekrar-dene') ? ['bekliyor','hata'] : ['bekliyor'];
    $kayitlar = Ek2Record::with(['workplace','doctor'])->whereIn('status',['completed','cancelled'])->whereIn('ocr_status',$durumlar)
        ->orderBy('id')->limit((int) $this->option('limit'))->get();
    $disk = Storage::disk('local'); $ok = 0; $hata = 0;
    foreach ($kayitlar as $r) {
        try {
            if ($r->status === 'cancelled') {
                $vardi = false;
                foreach (["{$r->uuid}1.jpeg","{$r->uuid}2.jpeg","{$r->uuid}.json"] as $ad) if (is_file("{$hedef}/{$ad}")) { unlink("{$hedef}/{$ad}"); $vardi = true; }
                if ($vardi || $r->ocr_exported_at) {
                    $iptal = json_encode(['kayit_uuid'=>$r->uuid,'iptal_zamani'=>$r->cancelled_at?->toIso8601String(),'neden'=>$r->cancel_reason], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
                    if (file_put_contents("{$hedef}/.{$r->uuid}.iptal.json.tmp", $iptal) === false || !rename("{$hedef}/.{$r->uuid}.iptal.json.tmp", "{$hedef}/{$r->uuid}.iptal.json")) throw new RuntimeException('iptal işareti yazılamadı');
                }
                $r->update(['ocr_status'=>'aktarildi','ocr_exported_at'=>now(),'ocr_error'=>null]);
                AuditLog::kaydet('ocr_iptal_aktar',$r); $ok++; continue;
            }
            $sayfalar = [];
            foreach (['1'=>$r->front_path,'2'=>$r->back_path] as $no=>$yol) {
                if (!$yol || !$disk->exists($yol)) throw new RuntimeException("sayfa {$no} dosyası bulunamadı");
                $ad = "{$r->uuid}{$no}.jpeg";
                if (!copy($disk->path($yol), "{$hedef}/.{$ad}.tmp") || !rename("{$hedef}/.{$ad}.tmp", "{$hedef}/{$ad}")) throw new RuntimeException("sayfa {$no} kopyalanamadı");
                $sayfalar[$no] = $ad;
            }
            $kunye = [
                'kayit_uuid'=>$r->uuid, 'sayfa1'=>$sayfalar['1'], 'sayfa2'=>$sayfalar['2'],
                'tc_kimlik_no'=>$r->tc_kimlik_no, 'ad'=>$r->first_name, 'soyad'=>$r->last_name,
                'isyeri'=>['id'=>$r->workplace->id, 'ad'=>$r->workplace->name, 'sgk_sicil_no'=>$r->workplace->sgk_sicil_no, 'adres'=>$r->workplace->address],
                'doktor'=>['id'=>$r->doctor->id, 'ad_soyad'=>trim($r->doctor->name.' '.$r->doctor->surname)],
                'yukleme_zamani'=>$r->completed_at?->toIso8601String(),
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

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Ek2Record extends Model
{
    protected $fillable = ['doctor_id','workplace_id','source','legacy_form_id','review_note','retake_pages','retake_note','tc_kimlik_no','first_name','last_name','exam_date','exam_date_source','front_path','back_path','latitude','longitude','location_source','location_accuracy_m','address','address_at','device_make','device_model','device_user_agent','status','completed_at','cancelled_at','cancel_reason','ocr_status','ocr_exported_at','ocr_error'];
    protected $hidden = ['tc_kimlik_no'];
    protected function casts(): array { return ['completed_at'=>'datetime','cancelled_at'=>'datetime','exam_date'=>'date','address_at'=>'datetime','retake_pages'=>'array','latitude'=>'float','longitude'=>'float','ocr_exported_at'=>'datetime']; }
    public function doctor(): BelongsTo { return $this->belongsTo(User::class, 'doctor_id'); }
    public function workplace(): BelongsTo { return $this->belongsTo(Workplace::class); }
    public function getMaskedTcAttribute(): string {
        $tc = (string) $this->tc_kimlik_no;
        return strlen($tc) === 11 ? substr($tc,0,2).'*******'.substr($tc,-2) : '***********';
    }
    /** @return string[] eksik sayfalar: 'ön' / 'arka' */
    public function getMissingPagesAttribute(): array { return array_keys(array_filter(['ön'=>!$this->front_path,'arka'=>!$this->back_path])); }
    /** @return string[] yeniden çekilmesi istenen sayfalar: 'ön' / 'arka' */
    public function getRetakeLabelsAttribute(): array { return array_values(array_map(fn($s)=>$s==='front'?'ön':'arka', $this->retake_pages ?? [])); }
    public function getDeviceLabelAttribute(): ?string { $m=trim(($this->device_make ?? '').' '.($this->device_model ?? '')); return $m!=='' ? $m : null; }
    public function getSourceLabelAttribute(): string { return $this->source==='tarama' ? 'Taranmış (eski)' : 'Telefon'; }
    public function getMapUrlAttribute(): ?string { return $this->latitude===null ? null : "https://www.openstreetmap.org/?mlat={$this->latitude}&mlon={$this->longitude}#map=18/{$this->latitude}/{$this->longitude}"; }
    public function getStatusLabelAttribute(): string { return ['draft'=>'Taslak','completed'=>'Tamamlandı','cancelled'=>'İptal'][$this->status] ?? $this->status; }
    public function getOcrLabelAttribute(): string { return ['bekliyor'=>'Bekliyor','aktarildi'=>'Aktarıldı','hata'=>'Hata'][$this->ocr_status] ?? $this->ocr_status; }
    // Fotoğraf yalnız yetki kontrolünden sonra controller üzerinden verilir; tarayıcı önbelleğe almaz
    public function imageResponse(string $side): BinaryFileResponse {
        abort_unless(in_array($side,['front','back'],true),404); $path=$side==='front'?$this->front_path:$this->back_path;
        abort_unless($path && Storage::disk('local')->exists($path),404);
        return response()->file(Storage::disk('local')->path($path),['Cache-Control'=>'private, no-store']);
    }
    // Doktorun tamamlaması gereken kayıtlar: sayfası eksik ya da yeniden çekilmesi istenen (iptal edilmemiş)
    public function scopeEksik($q) { return $q->where('status','!=','cancelled')->where(fn($q)=>$q->whereNull('front_path')->orWhereNull('back_path')->orWhereNotNull('retake_pages')); }
    // Ad / soyad (Türkçe harf duyarsız: İ=i, I=ı), TC başı ya da OCR form adı ile arama
    public function scopeAra($q, ?string $s) {
        $s=trim((string)$s); if ($s==='') return $q;
        if (preg_match('/^\d+$/',$s)) return $q->where('tc_kimlik_no','like',$s.'%');
        $like='%'.addcslashes($s,'%_\\').'%';
        return $q->where(fn($q)=>$q->whereRaw("CONCAT(first_name,' ',last_name) COLLATE utf8mb4_turkish_ci LIKE ?",[$like])->orWhere('legacy_form_id','like',$like));
    }
    // Sayfayı 90° çevirir. Önce kayıpsız: yalnız EXIF yön etiketi değişir (pikseller, GPS ve cihaz bilgisi korunur);
    // etiket yoksa ya da aynalıysa GD ile yeniden kaydeder. Orijinal silinmez, yeni sürüm aynı klasöre yazılır ve kayıt ona geçer.
    public function rotatePage(string $side, string $yon): void {
        abort_unless(in_array($side,['front','back'],true) && in_array($yon,['sol','sag'],true),404);
        $col=$side==='front'?'front_path':'back_path'; abort_unless($this->$col,404);
        $disk=Storage::disk('local'); $kaynak=$disk->path($this->$col);
        $yol=dirname($this->$col).'/'.$side.'-'.now()->format('Ymd-His').'-dondu.jpeg'; $hedef=$disk->path($yol);
        $bayt=file_get_contents($kaynak); $yeniBayt=\App\Support\ExifYon::dondur($bayt,$yon==='sag');
        if ($yeniBayt!==null) { abort_unless(file_put_contents($hedef,$yeniBayt)!==false,500,'Döndürülen fotoğraf yazılamadı.'); }
        else {
            $img=@imagecreatefromstring($bayt); abort_unless($img,422,'Fotoğraf okunamadı.');
            $o=(int)(@exif_read_data($kaynak,'IFD0')['Orientation'] ?? 1);
            $aci=([3=>180,6=>-90,8=>90][$o] ?? 0)+($yon==='sol'?90:-90); // imagerotate saat yönünün tersine döndürür
            if ($aci%360!==0) { $y=imagerotate($img,$aci,0); imagedestroy($img); $img=$y; }
            abort_unless(imagejpeg($img,$hedef,95),500,'Döndürülen fotoğraf yazılamadı.'); imagedestroy($img);
        }
        $this->update([$col=>$yol,'ocr_status'=>$this->ocr_status==='aktarildi' ? 'bekliyor' : $this->ocr_status]);
    }
    // Mükerrer şüphesi: iptal edilmemiş, aynı TC + aynı muayene tarihli birden çok kayıt (aynı kişinin aynı gün iki EK-2'si olamaz)
    public function scopeMukerrer($q) {
        return $q->where('status','!=','cancelled')->whereNotNull('exam_date')->where('tc_kimlik_no','!=','')
            ->whereRaw("(tc_kimlik_no, exam_date) IN (SELECT tc_kimlik_no, exam_date FROM ek2_records WHERE status <> 'cancelled' AND exam_date IS NOT NULL AND tc_kimlik_no <> '' GROUP BY tc_kimlik_no, exam_date HAVING COUNT(*) > 1)");
    }
    // TC çakışması: aynı TC farklı ad-soyadla başka bir kayıtta (bir TC iki kişiye ait olamaz → TC ya da isim yanlış girilmiş)
    public function scopeTcCakisma($q) {
        return $q->where('status','!=','cancelled')->where('tc_kimlik_no','!=','')
            ->whereRaw("tc_kimlik_no IN (SELECT tc_kimlik_no FROM ek2_records WHERE status <> 'cancelled' AND tc_kimlik_no <> '' GROUP BY tc_kimlik_no HAVING COUNT(DISTINCT LOWER(CONCAT(TRIM(first_name),' ',TRIM(last_name))) COLLATE utf8mb4_turkish_ci) > 1)");
    }
    /** Aynı kişinin (TC) diğer kayıtları */
    public function digerKayitlar() { return $this->tc_kimlik_no==='' ? collect() : static::with('workplace')->where('tc_kimlik_no',$this->tc_kimlik_no)->where('id','!=',$this->id)->orderByDesc('exam_date')->orderByDesc('created_at')->get(); }
    public const SIRALAMALAR = ['yeni'=>'En yeni','eski'=>'En eski','ad'=>'Ada göre (A→Z)','ad_ters'=>'Ada göre (Z→A)','soyad'=>'Soyada göre (A→Z)'];
    // Türkçe alfabe sırası (Ç, Ğ, İ, Ö, Ş, Ü doğru yerde)
    public function scopeSirala($q, ?string $s) {
        $tr=fn($c,$y='asc')=>$q->orderByRaw("$c COLLATE utf8mb4_turkish_ci $y");
        return match ($s) {
            'eski'=>$q->oldest()->orderBy('id'),
            'ad'=>$tr('first_name')->orderByRaw('last_name COLLATE utf8mb4_turkish_ci'),
            'ad_ters'=>$tr('first_name','desc')->orderByRaw('last_name COLLATE utf8mb4_turkish_ci desc'),
            'soyad'=>$tr('last_name')->orderByRaw('first_name COLLATE utf8mb4_turkish_ci'),
            default=>$q->latest()->orderByDesc('id'),
        };
    }
    protected static function booted(): void {
        static::creating(function (Ek2Record $record) { if (!$record->uuid) $record->uuid = (string) Str::uuid(); });
    }
}

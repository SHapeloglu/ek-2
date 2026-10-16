<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Ek2Record extends Model
{
    protected $fillable = ['doctor_id','workplace_id','tc_kimlik_no','first_name','last_name','front_path','back_path','status','completed_at','cancelled_at','cancel_reason','ocr_status','ocr_exported_at','ocr_error'];
    protected $hidden = ['tc_kimlik_no'];
    protected function casts(): array { return ['completed_at'=>'datetime','cancelled_at'=>'datetime','ocr_exported_at'=>'datetime']; }
    public function doctor(): BelongsTo { return $this->belongsTo(User::class, 'doctor_id'); }
    public function workplace(): BelongsTo { return $this->belongsTo(Workplace::class); }
    public function getMaskedTcAttribute(): string {
        $tc = (string) $this->tc_kimlik_no;
        return strlen($tc) === 11 ? substr($tc,0,2).'*******'.substr($tc,-2) : '***********';
    }
    public function getStatusLabelAttribute(): string { return ['draft'=>'Taslak','completed'=>'Tamamlandı','cancelled'=>'İptal'][$this->status] ?? $this->status; }
    public function getOcrLabelAttribute(): string { return ['bekliyor'=>'Bekliyor','aktarildi'=>'Aktarıldı','hata'=>'Hata'][$this->ocr_status] ?? $this->ocr_status; }
    // Fotoğraf yalnız yetki kontrolünden sonra controller üzerinden verilir; tarayıcı önbelleğe almaz
    public function imageResponse(string $side): BinaryFileResponse {
        abort_unless(in_array($side,['front','back'],true),404); $path=$side==='front'?$this->front_path:$this->back_path;
        abort_unless($path && Storage::disk('local')->exists($path),404);
        return response()->file(Storage::disk('local')->path($path),['Cache-Control'=>'private, no-store']);
    }
    protected static function booted(): void {
        static::creating(function (Ek2Record $record) { if (!$record->uuid) $record->uuid = (string) Str::uuid(); });
    }
}

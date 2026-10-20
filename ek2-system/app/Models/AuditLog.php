<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AuditLog extends Model {
    public $timestamps = false;
    protected $fillable = ['user_id','action','entity_type','entity_id','details','ip_address','user_agent','created_at'];
    protected function casts(): array { return ['created_at'=>'datetime','details'=>'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    // Kim, neyi, ne zaman: kayıt görüntüleme / foto açma / oluşturma / düzeltme / iptal / giriş / OCR aktarımı
    public static function kaydet(string $action, ?Model $entity=null, ?array $details=null, ?int $userId=null): void {
        $req = app()->runningInConsole() ? null : request();
        static::create(['user_id'=>$userId ?? auth()->id(),'action'=>$action,'entity_type'=>$entity?->getTable(),'entity_id'=>$entity?->getKey(),
            'details'=>$details,'ip_address'=>$req?->ip(),'user_agent'=>$req ? mb_substr((string) $req->userAgent(),0,500) : null,'created_at'=>now()]);
    }
}

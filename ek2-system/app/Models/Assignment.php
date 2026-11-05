<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model {
    protected $fillable = ['seri_id','doctor_id','workplace_id','starts_at','ends_at','note','created_by'];
    protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime']; }
    public function doctor(): BelongsTo { return $this->belongsTo(User::class, 'doctor_id'); }
    public function workplace(): BelongsTo { return $this->belongsTo(Workplace::class); }
}

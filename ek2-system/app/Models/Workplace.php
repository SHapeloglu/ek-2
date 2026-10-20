<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Workplace extends Model {
    protected $fillable = ['name','sgk_sicil_no','address','is_active'];
    protected function casts(): array { return ['is_active'=>'boolean']; }
    public function records(): HasMany { return $this->hasMany(Ek2Record::class); }
}

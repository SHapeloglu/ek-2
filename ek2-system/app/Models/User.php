<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name','surname','email','password','role','is_active'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean']; }
    public function records(): HasMany { return $this->hasMany(Ek2Record::class, 'doctor_id'); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isDoctor(): bool { return $this->role === 'doctor'; }
}

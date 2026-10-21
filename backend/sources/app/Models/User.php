<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nama',
        'email',
        'password_hash',
        'role',
        'status_akun',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
        ];
    }

    /**
     * Nama kolom password untuk sistem autentikasi Laravel.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    // Accessor / Mutator untuk kompatibilitas Laravel standar 'name'
    public function getNameAttribute(): ?string
    {
        return $this->attributes['nama'] ?? null;
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['nama'] = $value;
    }

    // Accessor / Mutator untuk kompatibilitas Laravel standar 'password'
    public function getPasswordAttribute(): ?string
    {
        return $this->attributes['password_hash'] ?? null;
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password_hash'] = bcrypt($value);
    }

    /* ================= RELASI ELOQUENT ================= */

    public function adopter(): HasOne
    {
        return $this->hasOne(Adopter::class, 'id_user', 'id_user');
    }

    public function screeningAdopsi(): HasMany
    {
        return $this->hasMany(ScreeningAdopsi::class, 'id_user', 'id_user');
    }

    public function artikel(): HasMany
    {
        return $this->hasMany(Artikel::class, 'id_user', 'id_user');
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'id_user', 'id_user');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_user', 'id_user');
    }
}

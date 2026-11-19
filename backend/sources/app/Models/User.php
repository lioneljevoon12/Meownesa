<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_ADOPTER = 'adopter';

    /** Hak akses (permission) per role, ditampilkan di GET /api/auth/me untuk frontend. */
    public const AKSES = [
        self::ROLE_SUPERADMIN => ['akun.kelola', 'role.kelola', 'audit.lihat', 'konfigurasi.kelola'],
        self::ROLE_ADMIN => [
            'dashboard.lihat', 'kucing.kelola', 'feeding.kelola', 'kesehatan.kelola',
            'adopsi.proses', 'serah-terima.kelola', 'pasca-adopsi.kelola',
            'artikel.kelola', 'notifikasi.kirim',
        ],
        self::ROLE_ADOPTER => ['pengingat.terima'],
    ];

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

    public function isActive(): bool
    {
        return $this->status_akun === 'aktif';
    }

    /** @return array<int, string> */
    public function akses(): array
    {
        return self::AKSES[$this->role] ?? [];
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

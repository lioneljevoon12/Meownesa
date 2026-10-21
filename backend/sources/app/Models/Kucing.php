<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kucing extends Model
{
    use HasFactory;

    protected $table = 'kucing';
    protected $primaryKey = 'id_kucing';

    protected $fillable = [
        'id_lokasi',
        'kode_kucing',
        'nama_kucing',
        'jenis_kelamin',
        'kelompok_usia',
        'status_steril',
        'status_kucing',
        'deskripsi',
    ];

    /* ================= RELASI ELOQUENT ================= */

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'id_lokasi', 'id_lokasi');
    }

    public function fotoKucing(): HasMany
    {
        return $this->hasMany(FotoKucing::class, 'id_kucing', 'id_kucing');
    }

    public function fotoUtama(): HasOne
    {
        return $this->hasOne(FotoKucing::class, 'id_kucing', 'id_kucing')->where('is_utama', true);
    }

    public function pemberianMakan(): HasMany
    {
        return $this->hasMany(PemberianMakan::class, 'id_kucing', 'id_kucing');
    }

    public function riwayatKesehatan(): HasMany
    {
        return $this->hasMany(RiwayatKesehatan::class, 'id_kucing', 'id_kucing');
    }

    public function pengajuanAdopsi(): HasMany
    {
        return $this->hasMany(PengajuanAdopsi::class, 'id_kucing', 'id_kucing');
    }
}

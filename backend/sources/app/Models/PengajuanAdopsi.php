<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanAdopsi extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_adopsi';
    protected $primaryKey = 'id_pengajuan';

    protected $fillable = [
        'id_kucing',
        'id_adopter',
        'nomor_pengajuan',
        'kode_tracking',
        'nama_pemohon',
        'nomor_whatsapp',
        'email',
        'alamat',
        'status_pengajuan',
        'tanggal_pengajuan',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
    ];

    /* ================= RELASI ELOQUENT ================= */

    public function kucing(): BelongsTo
    {
        return $this->belongsTo(Kucing::class, 'id_kucing', 'id_kucing');
    }

    public function adopter(): BelongsTo
    {
        return $this->belongsTo(Adopter::class, 'id_adopter', 'id_adopter');
    }

    public function screeningAdopsi(): HasOne
    {
        return $this->hasOne(ScreeningAdopsi::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function lampiranPengajuan(): HasMany
    {
        return $this->hasMany(LampiranPengajuan::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function serahTerima(): HasOne
    {
        return $this->hasOne(SerahTerima::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'id_pengajuan', 'id_pengajuan');
    }
}

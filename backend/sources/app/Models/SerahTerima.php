<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerahTerima extends Model
{
    use HasFactory;

    /** Mencatat serah terima berarti proses selesai (MEOW-BR-07); satu-satunya nilai yang dipakai. */
    public const STATUS_SELESAI = 'Selesai';

    protected $table = 'serah_terima';
    protected $primaryKey = 'id_serah_terima';

    protected $fillable = [
        'id_pengajuan',
        'tanggal_serah_terima',
        'status_serah_terima',
        'dokumen_perjanjian',
        'foto_serah_terima',
    ];

    protected $casts = [
        'tanggal_serah_terima' => 'date:Y-m-d',
    ];

    public function pengajuanAdopsi(): BelongsTo
    {
        return $this->belongsTo(PengajuanAdopsi::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function pascaAdopsi(): HasMany
    {
        return $this->hasMany(PascaAdopsi::class, 'id_serah_terima', 'id_serah_terima');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kabar kondisi kucing yang dikirim adopter sendiri (dev-backend-17). Baru menjadi catatan pemantauan (pasca_adopsi)
 * setelah ditinjau dan dicatat admin, sesuai MEOW-BR-13.
 */
class LaporanAdopter extends Model
{
    public const MENUNGGU = 'MENUNGGU';
    public const DICATAT = 'DICATAT';
    public const DITOLAK = 'DITOLAK';
    public const STATUS = [self::MENUNGGU, self::DICATAT, self::DITOLAK];

    /** Laporan yang belum ditinjau per adopsi dibatasi agar tidak dibanjiri. */
    public const MAKS_MENUNGGU = 3;

    protected $table = 'laporan_adopter';

    protected $primaryKey = 'id_laporan';

    protected $fillable = [
        'id_serah_terima', 'id_user', 'tanggal_laporan', 'status_kondisi', 'catatan',
        'status_laporan', 'id_pasca_adopsi', 'catatan_admin', 'ditinjau_oleh', 'ditinjau_pada',
    ];

    protected $casts = [
        'tanggal_laporan' => 'date:Y-m-d',
        'ditinjau_pada'   => 'datetime',
    ];

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(SerahTerima::class, 'id_serah_terima', 'id_serah_terima');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}

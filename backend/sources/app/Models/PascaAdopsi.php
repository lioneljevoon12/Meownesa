<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PascaAdopsi extends Model
{
    use HasFactory;

    /**
     * Kondisi kucing setelah diadopsi. `Meninggal` mengakhiri pemantauan; `Hilang` tidak (kucing bisa
     * ditemukan kembali, dan pemantauan justru perlu berlanjut).
     */
    public const STATUS = ['Sehat', 'Perawatan', 'Sakit', 'Hilang', 'Meninggal'];

    public const STATUS_AKHIR = ['Meninggal'];

    /** Pembaruan kondisi diharapkan tiap minggu (proposal 2.1.2 dan MEOW-F-28). */
    public const INTERVAL_HARI = 7;

    /** Masa pemantauan sejak serah terima; sesudahnya pemantauan dianggap selesai (tidak lagi jatuh tempo). */
    public const MASA_BULAN = 6;

    protected $table = 'pasca_adopsi';
    protected $primaryKey = 'id_pasca_adopsi';

    protected $fillable = [
        'id_serah_terima',
        'tanggal_update',
        'status_kondisi',
        'catatan',
    ];

    protected $casts = [
        // Format eksplisit: tersimpan 'Y-m-d' di semua basis data (lihat catatan di PemberianMakan).
        'tanggal_update' => 'date:Y-m-d',
    ];

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(SerahTerima::class, 'id_serah_terima', 'id_serah_terima');
    }
}

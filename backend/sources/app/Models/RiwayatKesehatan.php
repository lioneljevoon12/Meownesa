<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapusan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKesehatan extends Model
{
    use HasFactory, MencatatPenghapusan;

    /**
     * Jenis tindakan (MEOW-F-11: vaksinasi, sterilisasi, pemeriksaan, vitamin; roadmap menambah obat
     * cacing/kutu dan penanganan luka). Detail seperti "Tricat" atau nama klinik ditulis di catatan.
     */
    public const JENIS = [
        'Sterilisasi', 'Vaksinasi', 'Pemeriksaan', 'Pemberian Vitamin',
        'Obat Cacing', 'Obat Kutu', 'Penanganan Luka', 'Lainnya',
    ];

    public const JENIS_STERILISASI = 'Sterilisasi';

    /** Tanggal tindakan dinilai menurut waktu setempat Jaga Satwa, bukan UTC bawaan aplikasi. */
    public const ZONA_WAKTU = 'Asia/Jakarta';

    protected $table = 'riwayat_kesehatan';
    protected $primaryKey = 'id_kesehatan';

    protected $fillable = [
        'client_uuid',
        'id_kucing',
        'jenis_tindakan',
        'tanggal',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function kucing(): BelongsTo
    {
        return $this->belongsTo(Kucing::class, 'id_kucing', 'id_kucing');
    }
}

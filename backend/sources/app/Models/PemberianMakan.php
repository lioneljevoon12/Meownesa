<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapusan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemberianMakan extends Model
{
    use HasFactory, MencatatPenghapusan;

    /** Tuntas = sudah diberi (spreadsheet: Done), Belum = belum diberi (Not yet), Terjadwal = rencana hari ini/mendatang. */
    public const STATUS = ['Tuntas', 'Belum', 'Terjadwal'];

    /** Tanggal pemberian makan dinilai menurut waktu setempat Jaga Satwa, bukan UTC bawaan aplikasi. */
    public const ZONA_WAKTU = 'Asia/Jakarta';

    protected $table = 'pemberian_makan';
    protected $primaryKey = 'id_pemberian';

    protected $fillable = [
        'client_uuid',
        'id_kucing',
        'id_lokasi',
        'tanggal',
        'status_pemberian',
        'catatan',
    ];

    protected $casts = [
        // Format eksplisit: tersimpan persis 'Y-m-d' di semua basis data (SQLite menyimpan cast 'date'
        // polos sebagai 'Y-m-d 00:00:00', sehingga perbandingan tanggal berbeda dari MySQL).
        'tanggal' => 'date:Y-m-d',
    ];

    public function kucing(): BelongsTo
    {
        return $this->belongsTo(Kucing::class, 'id_kucing', 'id_kucing');
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'id_lokasi', 'id_lokasi');
    }
}

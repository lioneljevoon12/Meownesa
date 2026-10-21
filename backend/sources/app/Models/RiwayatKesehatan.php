<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKesehatan extends Model
{
    use HasFactory;

    protected $table = 'riwayat_kesehatan';
    protected $primaryKey = 'id_kesehatan';

    protected $fillable = [
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemberianMakan extends Model
{
    use HasFactory;

    protected $table = 'pemberian_makan';
    protected $primaryKey = 'id_pemberian';

    protected $fillable = [
        'id_kucing',
        'id_lokasi',
        'tanggal',
        'status_pemberian',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
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

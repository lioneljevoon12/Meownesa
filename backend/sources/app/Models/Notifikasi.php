<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasi';
    protected $primaryKey = 'id_notifikasi';

    protected $fillable = [
        'id_pengajuan',
        'id_user',
        'jenis_notifikasi',
        'kanal',
        'isi_pesan',
        'status_pengiriman',
        'tanggal_dikirim',
    ];

    protected $casts = [
        'tanggal_dikirim' => 'date',
    ];

    public function pengajuanAdopsi(): BelongsTo
    {
        return $this->belongsTo(PengajuanAdopsi::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}

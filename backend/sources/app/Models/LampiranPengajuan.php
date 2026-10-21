<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LampiranPengajuan extends Model
{
    use HasFactory;

    protected $table = 'lampiran_pengajuan';
    protected $primaryKey = 'id_lampiran';

    protected $fillable = [
        'id_pengajuan',
        'jenis_lampiran',
        'url_file',
    ];

    public function pengajuanAdopsi(): BelongsTo
    {
        return $this->belongsTo(PengajuanAdopsi::class, 'id_pengajuan', 'id_pengajuan');
    }
}

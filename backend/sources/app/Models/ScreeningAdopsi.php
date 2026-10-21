<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningAdopsi extends Model
{
    use HasFactory;

    protected $table = 'screening_adopsi';
    protected $primaryKey = 'id_screening';

    protected $fillable = [
        'id_pengajuan',
        'id_user',
        'kelayakan_hunian',
        'kestabilan_ekonomi',
        'pemahaman_perawatan',
        'sikap_proklinik',
        'hasil_screening',
        'tanggal_screening',
    ];

    protected $casts = [
        'tanggal_screening' => 'date',
    ];

    public function pengajuanAdopsi(): BelongsTo
    {
        return $this->belongsTo(PengajuanAdopsi::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}

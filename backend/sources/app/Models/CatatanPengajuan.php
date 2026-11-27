<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan internal admin pada sebuah pengajuan adopsi (dev-backend-17). Tidak pernah terlihat pelamar. */
class CatatanPengajuan extends Model
{
    public const JENIS = ['Catatan', 'Kunjungan Rumah'];

    protected $table = 'catatan_pengajuan';

    protected $primaryKey = 'id_catatan';

    protected $fillable = ['id_pengajuan', 'id_user', 'jenis', 'isi', 'tanggal_kunjungan'];

    protected $casts = ['tanggal_kunjungan' => 'date:Y-m-d'];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanAdopsi::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}

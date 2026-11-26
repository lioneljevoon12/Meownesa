<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanAdopsi extends Model
{
    use HasFactory;

    public const STATUS = ['MENUNGGU', 'SCREENING', 'DISETUJUI', 'DITOLAK', 'SELESAI'];

    /** Pengajuan yang masih berjalan: belum diputuskan admin. */
    public const STATUS_AKTIF = ['MENUNGGU', 'SCREENING'];

    /** Teks status untuk calon adopter di halaman pelacakan: [label, keterangan]. */
    public const TEKS_PUBLIK = [
        'MENUNGGU'  => ['Menunggu Pemeriksaan', 'Pengajuan Anda sudah kami terima dan menunggu diperiksa oleh pengurus Jaga Satwa.'],
        'SCREENING' => ['Sedang Diperiksa', 'Pengurus Jaga Satwa sedang memeriksa pengajuan Anda. Mohon tetap aktif di WhatsApp dan email.'],
        'DISETUJUI' => ['Disetujui', 'Selamat! Pengajuan Anda disetujui. Pengurus akan menghubungi Anda untuk proses serah terima.'],
        'DITOLAK'   => ['Tidak Disetujui', 'Mohon maaf, pengajuan Anda belum dapat kami setujui. Terima kasih atas minat dan kepedulian Anda.'],
        'SELESAI'   => ['Adopsi Selesai', 'Serah terima telah dicatat. Terima kasih sudah memberi rumah baru bagi kucing kampus!'],
    ];

    protected $table = 'pengajuan_adopsi';
    protected $primaryKey = 'id_pengajuan';

    protected $fillable = [
        'id_kucing',
        'id_adopter',
        'nomor_pengajuan',
        'kode_tracking',
        'nama_pemohon',
        'nomor_whatsapp',
        'email',
        'alamat',
        'status_pengajuan',
        'tanggal_pengajuan',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
    ];

    /* ================= RELASI ELOQUENT ================= */

    public function kucing(): BelongsTo
    {
        return $this->belongsTo(Kucing::class, 'id_kucing', 'id_kucing');
    }

    public function adopter(): BelongsTo
    {
        return $this->belongsTo(Adopter::class, 'id_adopter', 'id_adopter');
    }

    public function screeningAdopsi(): HasOne
    {
        return $this->hasOne(ScreeningAdopsi::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function lampiranPengajuan(): HasMany
    {
        return $this->hasMany(LampiranPengajuan::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function serahTerima(): HasOne
    {
        return $this->hasOne(SerahTerima::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'id_pengajuan', 'id_pengajuan');
    }
}

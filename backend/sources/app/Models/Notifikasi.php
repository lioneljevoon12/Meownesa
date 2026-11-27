<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notifikasi extends Model
{
    use HasFactory;

    public const KANAL_EMAIL = 'Email';
    public const KANAL_WHATSAPP = 'WhatsApp';
    public const KANAL = [self::KANAL_WHATSAPP, self::KANAL_EMAIL];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_TERKIRIM = 'TERKIRIM';
    public const STATUS_GAGAL = 'GAGAL';
    public const STATUS = [self::STATUS_PENDING, self::STATUS_TERKIRIM, self::STATUS_GAGAL];

    public const JENIS_REMINDER = 'Reminder Monitoring';
    public const JENIS_UNDANGAN_PENGURUS = 'Undangan Pengurus';
    public const JENIS_RESET_PASSWORD = 'Reset Password';

    protected $table = 'notifikasi';
    protected $primaryKey = 'id_notifikasi';

    protected $fillable = [
        'id_pengajuan',
        'id_user',
        'jenis_notifikasi',
        'kanal',
        'kunci',
        'isi_pesan',
        'status_pengiriman',
        'percobaan',
        'catatan_galat',
        'tanggal_dikirim',
    ];

    protected $casts = [
        'tanggal_dikirim' => 'date:Y-m-d',
        'percobaan'       => 'integer',
    ];

    public function pengajuanAdopsi(): BelongsTo
    {
        return $this->belongsTo(PengajuanAdopsi::class, 'id_pengajuan', 'id_pengajuan');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Notifikasi yang boleh dilihat/dikelola Admin Jaga Satwa: hanya yang terkait pengajuan adopsi.
     * Notifikasi akun pengurus (undangan, reset password) memuat tautan aktivasi akun orang lain, jadi
     * HANYA diurus Super Admin; admin biasa tidak boleh membacanya (bisa dipakai membajak akun).
     */
    public function scopeUntukAdmin(Builder $query): Builder
    {
        return $query->whereNotNull('id_pengajuan');
    }

    /** @param array<string, mixed> $f */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status_pengiriman', $v))
            ->when($f['kanal'] ?? null, fn (Builder $q, $v) => $q->where('kanal', $v))
            ->when($f['jenis'] ?? null, fn (Builder $q, $v) => $q->where('jenis_notifikasi', $v))
            ->when($f['id_pengajuan'] ?? null, fn (Builder $q, $v) => $q->where('id_pengajuan', $v));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kucing extends Model
{
    use HasFactory;

    /** Nilai status_kucing yang dipakai (data presensi Jaga Satwa menambah "Hilang"). */
    public const STATUS = ['Terdata', 'Tersedia', 'Proses Screening', 'Diadopsi', 'Hilang', 'Almarhum'];

    /** Kucing yang masih berada di area kampus (dihitung pada statistik lokasi). */
    public const STATUS_DI_KAMPUS = ['Terdata', 'Tersedia', 'Proses Screening'];

    /** "Tidak Diketahui" dipakai bila spreadsheet tidak mencantumkan status steril. */
    public const STATUS_STERIL = ['Sudah', 'Belum', 'Tidak Diketahui'];

    /** Status yang boleh tampil di katalog publik (MEOW-NF-05); selain ini hanya terlihat admin. */
    public const STATUS_PUBLIK = ['Tersedia', 'Terdata'];

    /**
     * Status yang boleh diisi admin secara manual. "Proses Screening" dan "Diadopsi" diatur
     * otomatis oleh alur adopsi (MEOW-BR-07, modul 07-09), bukan lewat edit data kucing.
     */
    public const STATUS_MANUAL = ['Terdata', 'Tersedia', 'Hilang', 'Almarhum'];

    public const KELOMPOK_USIA = ['Kitten', 'Remaja', 'Dewasa'];

    /** Awalan kode kucing per kampus; kampus lain memakai KCG. */
    public const PREFIX_KODE = ['Kampus Ketintang' => 'KTT', 'Kampus Lidah Wetan' => 'LWT'];

    protected $table = 'kucing';
    protected $primaryKey = 'id_kucing';

    protected $fillable = [
        'id_lokasi',
        'kode_kucing',
        'nama_kucing',
        'jenis_kelamin',
        'kelompok_usia',
        'status_steril',
        'status_kucing',
        'deskripsi',
    ];

    public function statusLabel(): string
    {
        return match ($this->status_kucing) {
            'Tersedia'         => 'Siap Diadopsi',
            'Proses Screening' => 'Sedang Diproses',
            'Diadopsi'         => 'Sudah Diadopsi',
            'Hilang'           => 'Hilang',
            'Almarhum'         => 'Almarhum',
            default            => 'Terdata Kampus',
        };
    }

    /**
     * Terapkan filter katalog (dipakai API publik dan admin).
     * Kunci: q, id_kampus, id_lokasi, status, jenis_kelamin, kelompok_usia, status_steril, sort.
     *
     * @param  array<string, mixed>  $f
     */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        $query
            ->when($f['id_lokasi'] ?? null, fn (Builder $q, $v) => $q->where('id_lokasi', $v))
            ->when($f['id_kampus'] ?? null, fn (Builder $q, $v) => $q->whereHas('lokasi', fn (Builder $l) => $l->where('id_kampus', $v)))
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status_kucing', $v))
            ->when($f['jenis_kelamin'] ?? null, fn (Builder $q, $v) => $q->where('jenis_kelamin', $v))
            ->when($f['kelompok_usia'] ?? null, fn (Builder $q, $v) => $q->where('kelompok_usia', $v))
            ->when($f['status_steril'] ?? null, fn (Builder $q, $v) => $q->where('status_steril', $v))
            ->when(trim((string) ($f['q'] ?? '')) !== '', function (Builder $q) use ($f) {
                $like = '%'.addcslashes(trim((string) $f['q']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->where('nama_kucing', 'like', $like)
                    ->orWhere('kode_kucing', 'like', $like)
                    ->orWhere('deskripsi', 'like', $like)
                    ->orWhereHas('lokasi', fn (Builder $l) => $l->where('nama_lokasi', 'like', $like)));
            });

        return ($f['sort'] ?? 'nama') === 'terbaru'
            ? $query->orderByDesc('id_kucing')
            : $query->orderBy('nama_kucing')->orderBy('id_kucing');
    }

    /* ================= RELASI ELOQUENT ================= */

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(Lokasi::class, 'id_lokasi', 'id_lokasi');
    }

    public function fotoKucing(): HasMany
    {
        return $this->hasMany(FotoKucing::class, 'id_kucing', 'id_kucing');
    }

    public function fotoUtama(): HasOne
    {
        return $this->hasOne(FotoKucing::class, 'id_kucing', 'id_kucing')->where('is_utama', true);
    }

    public function pemberianMakan(): HasMany
    {
        return $this->hasMany(PemberianMakan::class, 'id_kucing', 'id_kucing');
    }

    public function riwayatKesehatan(): HasMany
    {
        return $this->hasMany(RiwayatKesehatan::class, 'id_kucing', 'id_kucing');
    }

    public function pengajuanAdopsi(): HasMany
    {
        return $this->hasMany(PengajuanAdopsi::class, 'id_kucing', 'id_kucing');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Artikel extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_ARCHIVED = 'ARCHIVED';
    public const STATUS = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    /** Kategori yang diizinkan (termasuk yang dipakai seeder). */
    public const KATEGORI = [
        'Edukasi & Kampanye',
        'Kesehatan Veteriner',
        'Panduan Adopsi',
        'Informasi Kegiatan',
        'Cerita Kucing',
    ];

    /** Slug yang bentrok dengan rute statis /api/artikel/{featured,kategori}. */
    public const SLUG_TERCADANG = ['featured', 'kategori'];

    protected $table = 'artikel';
    protected $primaryKey = 'id_artikel';

    protected $fillable = [
        'id_user',
        'judul',
        'slug',
        'kategori',
        'isi',
        'thumbnail_url',
        'status_publikasi',
        'is_featured',
        'is_pinned',
        'tanggal_dibuat',
        'tanggal_diterbitkan',
    ];

    protected $casts = [
        'is_featured'         => 'boolean',
        'is_pinned'           => 'boolean',
        'tanggal_dibuat'      => 'date:Y-m-d',
        'tanggal_diterbitkan' => 'date:Y-m-d',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /** Hanya yang terbit; satu-satunya yang boleh dilihat publik. */
    public function scopeDiterbitkan(Builder $query): Builder
    {
        return $query->where('status_publikasi', self::STATUS_PUBLISHED);
    }

    /** Urutan tampilan: yang disematkan dulu, lalu terbaru. */
    public function scopeUrutanTampil(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('tanggal_diterbitkan')->orderByDesc('id_artikel');
    }

    /** @param array<string, mixed> $f */
    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['kategori'] ?? null, fn (Builder $q, $v) => $q->where('kategori', $v))
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status_publikasi', $v))
            ->when(array_key_exists('featured', $f) && $f['featured'] !== null, fn (Builder $q) => $q->where('is_featured', (bool) $f['featured']))
            ->when(array_key_exists('pinned', $f) && $f['pinned'] !== null, fn (Builder $q) => $q->where('is_pinned', (bool) $f['pinned']))
            ->when(trim((string) ($f['q'] ?? '')) !== '', function (Builder $q) use ($f) {
                $like = '%'.addcslashes(trim((string) $f['q']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('judul', 'like', $like)->orWhere('isi', 'like', $like));
            });
    }

    /** Cuplikan teks polos untuk kartu daftar (maksimal $panjang karakter, sudah termasuk elipsis). */
    public function ringkasan(int $panjang = 160): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->isi) ?? ''), $panjang - 1, '…');
    }
}

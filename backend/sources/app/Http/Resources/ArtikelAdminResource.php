<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Artikel untuk admin: semua status, penulis, dan (pada detail) isi lengkap.
 *
 * @mixin \App\Models\Artikel
 */
class ArtikelAdminResource extends JsonResource
{
    private bool $denganIsi = false;

    /** Resource detail: menyertakan isi lengkap (daftar hanya memuat ringkasan agar ringan). */
    public static function detail(mixed $artikel): self
    {
        $r = new self($artikel);
        $r->denganIsi = true;

        return $r;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_artikel harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_artikel'          => $this->id_artikel,
            'judul'               => $this->judul,
            'slug'                => $this->slug,
            'kategori'            => $this->kategori,
            'status_publikasi'    => $this->status_publikasi,
            'is_featured'         => $this->is_featured,
            'is_pinned'           => $this->is_pinned,
            'thumbnail_url'       => $this->thumbnail_url ? asset($this->thumbnail_url) : null,
            'ringkasan'           => $this->ringkasan(),
            'isi'                 => $this->when($this->denganIsi, fn () => $this->isi),
            'penulis'             => $this->author ? ['id_user' => $this->author->id_user, 'nama' => $this->author->nama] : null,
            'tanggal_dibuat'      => $this->tanggal_dibuat?->format('Y-m-d'),
            'tanggal_diterbitkan' => $this->tanggal_diterbitkan?->format('Y-m-d'),
            'diperbarui_pada'     => $this->updated_at?->toIso8601String(),
        ];
    }
}

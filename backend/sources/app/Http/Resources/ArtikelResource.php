<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Artikel untuk publik (kartu daftar). Tidak memuat data penulis maupun status internal.
 *
 * @mixin \App\Models\Artikel
 */
class ArtikelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id_artikel'          => $this->id_artikel,
            'judul'               => $this->judul,
            'slug'                => $this->slug,
            'kategori'            => $this->kategori,
            'ringkasan'           => $this->ringkasan(),
            'thumbnail_url'       => $this->thumbnail_url ? asset($this->thumbnail_url) : null,
            'is_featured'         => $this->is_featured,
            'is_pinned'           => $this->is_pinned,
            'tanggal_diterbitkan' => $this->tanggal_diterbitkan?->format('Y-m-d'),
        ];
    }
}

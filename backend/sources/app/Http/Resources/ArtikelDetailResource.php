<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Artikel lengkap untuk publik. `isi` adalah teks polos/Markdown (tag HTML dibuang saat disimpan);
 * frontend merendernya sebagai teks atau Markdown, bukan sebagai HTML mentah.
 *
 * @mixin \App\Models\Artikel
 */
class ArtikelDetailResource extends ArtikelResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['isi' => $this->isi];
    }
}

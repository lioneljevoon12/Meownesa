<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data lokasi untuk publik dan admin. Hanya area umum kampus — tidak pernah memuat
 * alamat adopter (MEOW-F-39, MEOW-NF-06).
 *
 * @mixin \App\Models\Lokasi
 */
class LokasiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id_lokasi'     => $this->id_lokasi,
            'id_kampus'     => $this->id_kampus,
            'nama_lokasi'   => $this->nama_lokasi,
            'koordinat_x'   => $this->koordinat_x, // lintang (latitude)
            'koordinat_y'   => $this->koordinat_y, // bujur (longitude)
            'jumlah_kucing' => $this->whenCounted('kucing'),
            'kampus'        => $this->whenLoaded('kampus', fn () => [
                'id_kampus'   => $this->kampus->id_kampus,
                'nama_kampus' => $this->kampus->nama_kampus,
            ]),
        ];
    }
}

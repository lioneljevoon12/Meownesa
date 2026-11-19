<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Kampus */
class KampusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id_kampus'     => $this->id_kampus,
            'nama_kampus'   => $this->nama_kampus,
            'jumlah_lokasi' => $this->whenCounted('lokasi'),
            'jumlah_kucing' => $this->whenCounted('kucing'),
        ];
    }
}

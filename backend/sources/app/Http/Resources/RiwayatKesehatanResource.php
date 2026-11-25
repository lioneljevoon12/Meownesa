<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catatan kesehatan untuk PUBLIK: hanya tanggal, jenis tindakan, dan catatan.
 *
 * @mixin \App\Models\RiwayatKesehatan
 */
class RiwayatKesehatanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'tanggal'        => $this->tanggal->format('Y-m-d'),
            'jenis_tindakan' => $this->jenis_tindakan,
            'catatan'        => $this->catatan,
        ];
    }
}

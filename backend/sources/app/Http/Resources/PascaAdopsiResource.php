<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PascaAdopsi */
class PascaAdopsiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_pasca_adopsi harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_pasca_adopsi' => $this->id_pasca_adopsi,
            'id_serah_terima' => $this->id_serah_terima,
            'tanggal_update'  => $this->tanggal_update->format('Y-m-d'),
            'status_kondisi'  => $this->status_kondisi,
            'catatan'         => $this->catatan,
            'dicatat_pada'    => $this->created_at?->toIso8601String(),
        ];
    }
}

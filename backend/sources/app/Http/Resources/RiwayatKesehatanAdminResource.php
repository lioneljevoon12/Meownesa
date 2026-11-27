<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Catatan kesehatan untuk ADMIN: memuat id (untuk ubah/hapus) dan timestamp. */
class RiwayatKesehatanAdminResource extends RiwayatKesehatanResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_kesehatan harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_kesehatan' => $this->id_kesehatan,
            'id_kucing'    => $this->id_kucing,
        ] + parent::toArray($request) + [
            'client_uuid'  => $this->client_uuid,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Data kucing untuk ADMIN: semua status plus timestamp dan jumlah foto. */
class KucingAdminResource extends KucingResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'jumlah_foto' => $this->whenCounted('fotoKucing'),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ]);
    }
}

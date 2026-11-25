<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FotoKucing */
class FotoKucingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_foto_kucing harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_foto_kucing' => $this->id_foto_kucing,
            'id_kucing'      => $this->id_kucing,
            'url'            => asset($this->url_foto),
            'is_utama'       => (bool) $this->is_utama,
        ];
    }
}

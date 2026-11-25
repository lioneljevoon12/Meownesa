<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PemberianMakan */
class PemberianMakanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_pemberian harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_pemberian'     => $this->id_pemberian,
            'client_uuid'      => $this->client_uuid,
            'tanggal'          => $this->tanggal->format('Y-m-d'),
            'status_pemberian' => $this->status_pemberian,
            'catatan'          => $this->catatan,
            'lokasi'           => $this->whenLoaded('lokasi', fn () => [
                'id_lokasi'   => $this->lokasi->id_lokasi,
                'nama_lokasi' => $this->lokasi->nama_lokasi,
                'id_kampus'   => $this->lokasi->id_kampus,
                'nama_kampus' => $this->lokasi->kampus?->nama_kampus,
            ]),
            'kucing'           => $this->whenLoaded('kucing', fn () => $this->kucing ? [
                'id_kucing'   => $this->kucing->id_kucing,
                'kode_kucing' => $this->kucing->kode_kucing,
                'nama_kucing' => $this->kucing->nama_kucing,
            ] : null),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}

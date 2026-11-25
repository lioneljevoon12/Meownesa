<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data kucing untuk PUBLIK. Hanya informasi umum — tidak ada data adopter, pengajuan,
 * rekam internal, maupun timestamp (MEOW-NF-04, NF-05, NF-06).
 *
 * @mixin \App\Models\Kucing
 */
class KucingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id_kucing'     => $this->id_kucing,
            'kode_kucing'   => $this->kode_kucing,
            'nama_kucing'   => $this->nama_kucing,
            'jenis_kelamin' => $this->jenis_kelamin,
            'kelompok_usia' => $this->kelompok_usia,
            'status_steril' => $this->status_steril,
            'status_kucing' => $this->status_kucing,
            'status_label'  => $this->statusLabel(),
            'deskripsi'     => $this->deskripsi,
            'lokasi'        => $this->whenLoaded('lokasi', fn () => [
                'id_lokasi'   => $this->lokasi->id_lokasi,
                'nama_lokasi' => $this->lokasi->nama_lokasi,
                'id_kampus'   => $this->lokasi->id_kampus,
                'nama_kampus' => $this->lokasi->kampus?->nama_kampus,
            ]),
            'foto_utama'    => $this->whenLoaded('fotoUtama', fn () => $this->fotoUtama ? asset($this->fotoUtama->url_foto) : null),
            'galeri'        => $this->whenLoaded('fotoKucing', fn () => $this->fotoKucing->map(fn ($f) => [
                'id_foto_kucing' => $f->id_foto_kucing,
                'url'            => asset($f->url_foto),
                'is_utama'       => (bool) $f->is_utama,
            ])->values()),
        ];
    }
}

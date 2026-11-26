<?php

namespace App\Http\Resources;

use App\Models\PengajuanAdopsi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pengajuan adopsi untuk ADMIN (daftar antrean). Memuat kontak pemohon karena admin berwenang
 * (MEOW-NF-04); tidak pernah dipakai di endpoint publik.
 *
 * @mixin \App\Models\PengajuanAdopsi
 */
class PengajuanAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_pengajuan harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_pengajuan'      => $this->id_pengajuan,
            'nomor_pengajuan'   => $this->nomor_pengajuan,
            'status_pengajuan'  => $this->status_pengajuan,
            'status_label'      => PengajuanAdopsi::TEKS_PUBLIK[$this->status_pengajuan][0] ?? $this->status_pengajuan,
            'tanggal_pengajuan' => $this->tanggal_pengajuan->format('Y-m-d'),
            'nama_pemohon'      => $this->nama_pemohon,
            'nomor_whatsapp'    => $this->nomor_whatsapp,
            'email'             => $this->email,
            'kucing'            => $this->whenLoaded('kucing', fn () => [
                'id_kucing'     => $this->kucing->id_kucing,
                'kode_kucing'   => $this->kucing->kode_kucing,
                'nama_kucing'   => $this->kucing->nama_kucing,
                'status_kucing' => $this->kucing->status_kucing,
                'foto_utama'    => $this->kucing->relationLoaded('fotoUtama') && $this->kucing->fotoUtama
                    ? asset($this->kucing->fotoUtama->url_foto) : null,
            ]),
            'jumlah_lampiran'   => $this->whenCounted('lampiranPengajuan'),
            'hasil_screening'   => $this->whenLoaded('screeningAdopsi', fn () => $this->screeningAdopsi?->hasil_screening),
        ];
    }
}

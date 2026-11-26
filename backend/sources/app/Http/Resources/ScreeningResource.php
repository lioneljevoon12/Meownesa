<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ScreeningAdopsi */
class ScreeningResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_screening harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_screening'        => $this->id_screening,
            'id_pengajuan'        => $this->id_pengajuan,
            'kelayakan_hunian'    => $this->kelayakan_hunian,
            'kestabilan_ekonomi'  => $this->kestabilan_ekonomi,
            'pemahaman_perawatan' => $this->pemahaman_perawatan,
            'sikap_proklinik'     => $this->sikap_proklinik,
            'hasil_screening'     => $this->hasil_screening,
            'tanggal_screening'   => $this->tanggal_screening->format('Y-m-d'),
            'petugas'             => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id_user' => $this->admin->id_user,
                'nama'    => $this->admin->nama,
            ] : null),
            'diperbarui_pada'     => $this->updated_at?->toIso8601String(),
        ];
    }
}

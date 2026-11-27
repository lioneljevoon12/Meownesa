<?php

namespace App\Http\Resources;

use App\Models\PemberianMakan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CatatanPengajuan */
class CatatanPengajuanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_catatan harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_catatan'        => $this->id_catatan,
            'id_pengajuan'      => $this->id_pengajuan,
            'jenis'             => $this->jenis,
            'isi'               => $this->isi,
            'tanggal_kunjungan' => $this->tanggal_kunjungan?->format('Y-m-d'),
            'penulis'           => $this->penulis ? ['id_user' => $this->penulis->id_user, 'nama' => $this->penulis->nama] : null,
            'dibuat_pada'       => $this->created_at?->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String(),
        ];
    }
}

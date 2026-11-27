<?php

namespace App\Http\Resources;

use App\Models\PemberianMakan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Laporan adopter untuk admin: memuat kontak adopter (endpoint ini khusus admin) agar tindak lanjut bisa langsung.
 *
 * @mixin \App\Models\LaporanAdopter
 */
class LaporanAdopterAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $p = $this->serahTerima?->pengajuanAdopsi;
        $k = $p?->kucing;

        // id_laporan harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_laporan'      => $this->id_laporan,
            'id_serah_terima' => $this->id_serah_terima,
            'status_laporan'  => $this->status_laporan,
            'tanggal_laporan' => $this->tanggal_laporan->format('Y-m-d'),
            'status_kondisi'  => $this->status_kondisi,
            'catatan'         => $this->catatan,
            'kucing'          => $k ? ['id_kucing' => $k->id_kucing, 'kode_kucing' => $k->kode_kucing, 'nama_kucing' => $k->nama_kucing] : null,
            'adopter'         => $p ? ['nama' => $p->nama_pemohon, 'nomor_whatsapp' => $p->nomor_whatsapp, 'email' => $p->email] : null,
            'id_pasca_adopsi' => $this->id_pasca_adopsi,
            'catatan_admin'   => $this->catatan_admin,
            'ditinjau_pada'   => $this->ditinjau_pada?->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String(),
            'dikirim_pada'    => $this->created_at?->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String(),
        ];
    }
}

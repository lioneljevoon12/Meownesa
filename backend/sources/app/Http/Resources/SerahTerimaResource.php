<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serah terima untuk ADMIN. Memuat data pribadi adopter (admin berwenang); berkasnya hanya dapat
 * diunduh lewat endpoint ber-token. Token aktivasi akun TIDAK PERNAH ada di sini.
 *
 * @mixin \App\Models\SerahTerima
 */
class SerahTerimaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $unduh = fn (string $jenis) => url("/api/admin/serah-terima/{$this->id_serah_terima}/berkas/{$jenis}");
        $p = $this->pengajuanAdopsi;
        $adopter = $p?->adopter;

        // id_serah_terima harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_serah_terima'      => $this->id_serah_terima,
            'id_pengajuan'         => $this->id_pengajuan,
            'tanggal_serah_terima' => $this->tanggal_serah_terima->format('Y-m-d'),
            'status_serah_terima'  => $this->status_serah_terima,
            'berkas'               => [
                'dokumen_perjanjian' => ['ada' => (bool) $this->dokumen_perjanjian, 'unduh' => $this->dokumen_perjanjian ? $unduh('dokumen_perjanjian') : null],
                'foto_serah_terima'  => ['ada' => (bool) $this->foto_serah_terima, 'unduh' => $this->foto_serah_terima ? $unduh('foto_serah_terima') : null],
            ],
            'pengajuan'            => $p ? [
                'nomor_pengajuan' => $p->nomor_pengajuan,
                'nama_pemohon'    => $p->nama_pemohon,
                'nomor_whatsapp'  => $p->nomor_whatsapp,
                'email'           => $p->email,
                'status'          => $p->status_pengajuan,
            ] : null,
            'kucing'               => $p?->kucing ? [
                'id_kucing'     => $p->kucing->id_kucing,
                'kode_kucing'   => $p->kucing->kode_kucing,
                'nama_kucing'   => $p->kucing->nama_kucing,
                'status_kucing' => $p->kucing->status_kucing,
            ] : null,
            'adopter'              => $adopter ? [
                'id_adopter'              => $adopter->id_adopter,
                'status_adopter'          => $adopter->status_adopter,
                'tanggal_menjadi_adopter' => $adopter->tanggal_menjadi_adopter->format('Y-m-d'),
                'akun'                    => $adopter->user ? [
                    'id_user'     => $adopter->user->id_user,
                    'email'       => $adopter->user->email,
                    'status_akun' => $adopter->user->status_akun,
                ] : null,
            ] : null,
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}

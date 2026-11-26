<?php

namespace App\Http\Resources;

use App\Models\PengajuanAdopsi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pengajuan adopsi untuk PEMEGANG KODE TRACKING (calon adopter). Hanya memuat apa yang perlu
 * diketahui pengaju: status dan kemajuan. TIDAK pernah memuat email, WhatsApp, alamat, nama
 * lengkap, jalur berkas lampiran, hasil screening, maupun catatan admin (MEOW-BR-08, MEOW-NF-04).
 *
 * @mixin \App\Models\PengajuanAdopsi
 */
class PengajuanPublikResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        [$label, $keterangan] = PengajuanAdopsi::TEKS_PUBLIK[$this->status_pengajuan] ?? [$this->status_pengajuan, ''];
        $s = $this->status_pengajuan;

        return [
            'nomor_pengajuan'   => $this->nomor_pengajuan,
            'status'            => $s,
            'status_label'      => $label,
            'keterangan'        => $keterangan,
            'tahap'             => [
                ['nama' => 'Pengajuan diterima', 'selesai' => true],
                ['nama' => 'Pemeriksaan',        'selesai' => in_array($s, ['SCREENING', 'DISETUJUI', 'DITOLAK', 'SELESAI'], true)],
                ['nama' => 'Keputusan',          'selesai' => in_array($s, ['DISETUJUI', 'DITOLAK', 'SELESAI'], true)],
                ['nama' => 'Serah terima',       'selesai' => $s === 'SELESAI'],
            ],
            'tanggal_pengajuan' => $this->tanggal_pengajuan->format('Y-m-d'),
            'diperbarui_pada'   => $this->updated_at?->toIso8601String(),
            'nama_depan'        => explode(' ', trim($this->nama_pemohon))[0],
            'kucing'            => $this->whenLoaded('kucing', fn () => [
                'id_kucing'   => $this->kucing->id_kucing,
                'kode_kucing' => $this->kucing->kode_kucing,
                'nama_kucing' => $this->kucing->nama_kucing,
                'foto_utama'  => $this->kucing->relationLoaded('fotoUtama') && $this->kucing->fotoUtama
                    ? asset($this->kucing->fotoUtama->url_foto) : null,
            ]),
            'lampiran'          => $this->whenLoaded('lampiranPengajuan', fn () => $this->lampiranPengajuan->map(fn ($l) => [
                'id_lampiran'    => $l->id_lampiran,
                'jenis_lampiran' => $l->jenis_lampiran,
            ])->values()),
        ];
    }
}

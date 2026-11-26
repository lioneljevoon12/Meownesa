<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Detail pengajuan untuk admin: alamat, kode tracking (untuk membantu pemohon yang lupa), lampiran, screening, notifikasi. */
class PengajuanAdminDetailResource extends PengajuanAdminResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $dasar = parent::toArray($request);
        // pada detail, hasil_screening diganti objek screening lengkap
        unset($dasar['hasil_screening']);

        return $dasar + [
            'alamat'          => $this->alamat,
            'kode_tracking'   => $this->kode_tracking,
            'lampiran'        => $this->whenLoaded('lampiranPengajuan', fn () => $this->lampiranPengajuan->map(fn ($l) => [
                'id_lampiran'    => $l->id_lampiran,
                'jenis_lampiran' => $l->jenis_lampiran,
                // Endpoint unduh khusus admin (butuh token); berkas sendiri tidak punya URL publik.
                'unduh'          => url("/api/admin/adopsi/{$this->id_pengajuan}/lampiran/{$l->id_lampiran}"),
            ])->values()),
            'screening'       => $this->whenLoaded('screeningAdopsi', fn () => $this->screeningAdopsi
                ? (new ScreeningResource($this->screeningAdopsi))->resolve() : null),
            'notifikasi'      => $this->whenLoaded('notifikasi', fn () => $this->notifikasi->map(fn ($n) => [
                'jenis_notifikasi'  => $n->jenis_notifikasi,
                'kanal'             => $n->kanal,
                'status_pengiriman' => $n->status_pengiriman,
                'dibuat_pada'       => $n->created_at?->toIso8601String(),
            ])->values()),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}

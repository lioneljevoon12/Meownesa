<?php

namespace App\Http\Resources;

use App\Models\Notifikasi;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Notifikasi */
class NotifikasiResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $p = $this->pengajuanAdopsi;

        // id_notifikasi harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_notifikasi'     => $this->id_notifikasi,
            'id_pengajuan'      => $this->id_pengajuan,
            'jenis_notifikasi'  => $this->jenis_notifikasi,
            'kanal'             => $this->kanal,
            'status_pengiriman' => $this->status_pengiriman,
            'percobaan'         => $this->percobaan,
            'catatan_galat'     => $this->catatan_galat,
            'tanggal_dikirim'   => $this->tanggal_dikirim?->format('Y-m-d'),
            'isi_pesan'         => $this->isi_pesan,
            // Penerima hanya terlihat oleh admin (endpoint ini khusus admin).
            'penerima'          => $p ? [
                'nama'           => $p->nama_pemohon,
                'nomor_whatsapp' => $p->nomor_whatsapp,
                'email'          => $p->email,
                'nomor_pengajuan' => $p->nomor_pengajuan,
            ] : null,
            // Tautan wa.me hanya berarti untuk pesan WhatsApp yang belum terkirim.
            'tautan_whatsapp'   => $this->kanal === Notifikasi::KANAL_WHATSAPP && $this->status_pengiriman !== Notifikasi::STATUS_TERKIRIM
                ? app(WhatsAppNotificationService::class)->tautan($this->resource)
                : null,
            'dibuat_pada'       => $this->created_at?->toIso8601String(),
        ];
    }
}

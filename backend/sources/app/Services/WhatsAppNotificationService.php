<?php

namespace App\Services;

use App\Models\Notifikasi;

/**
 * Pembentuk tautan WhatsApp `wa.me` (MEOW-F-32, MEOW-BR-12).
 *
 * WhatsApp hanya satu arah (notifikasi dan pengingat), bukan chat/CRM, dan tanpa gateway berbayar:
 * sistem menyiapkan pesan + tautan, admin Jaga Satwa membukanya lalu menekan "kirim" dari WhatsApp-nya
 * sendiri, kemudian menandai notifikasi sebagai terkirim lewat API.
 *
 * Bila kelak memakai gateway resmi, cukup tambahkan pengirim otomatis yang memanggil
 * {@see PengirimanNotifikasiService::tandaiWhatsApp()}; kontrak API dan data tidak berubah.
 */
class WhatsAppNotificationService
{
    /** Tautan `https://wa.me/<nomor>?text=<pesan>` atau null bila nomor penerima tidak tersedia. */
    public function tautan(Notifikasi $n): ?string
    {
        if ($n->kanal !== Notifikasi::KANAL_WHATSAPP) {
            return null;
        }

        $nomor = $this->nomorInternasional((string) $n->pengajuanAdopsi?->nomor_whatsapp);

        return $nomor === null ? null : 'https://wa.me/'.$nomor.'?text='.rawurlencode($n->isi_pesan);
    }

    /** Nomor tanpa `+`/spasi dalam format internasional Indonesia (628...), atau null bila tidak valid. */
    public function nomorInternasional(string $nomor): ?string
    {
        $digit = preg_replace('/\D+/', '', $nomor) ?? '';

        if (str_starts_with($digit, '0')) {
            $digit = '62'.substr($digit, 1);
        }

        return preg_match('/^628[1-9][0-9]{7,11}$/', $digit) === 1 ? $digit : null;
    }
}

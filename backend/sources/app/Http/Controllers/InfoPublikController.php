<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppNotificationService;
use Illuminate\Http\JsonResponse;

/**
 * Informasi resmi Jaga Satwa untuk halaman publik: rekening/QRIS donasi, tautan Google Form pelaporan,
 * dan kontak (MEOW-F-36, F-37). Nilainya dari .env (config/jagasatwa.php), bukan dari basis data.
 *
 * MEOW-BR-10: sistem TIDAK memproses pembayaran donasi; hanya menampilkan rekening/QRIS resmi.
 * MEOW-BR-11: form pelaporan kucing liar tidak dibuat ulang; pengunjung diarahkan ke Google Form resmi.
 */
class InfoPublikController extends Controller
{
    public function index(WhatsAppNotificationService $wa): JsonResponse
    {
        $donasi = config('jagasatwa.donasi');
        $qris = $this->url($donasi['qris_url']);
        $nomorWa = $wa->nomorInternasional((string) config('jagasatwa.kontak.whatsapp'));
        $instagram = ltrim(trim((string) config('jagasatwa.kontak.instagram')), '@');

        return response()->json([
            'success' => true,
            'data'    => [
                'donasi' => [
                    'tersedia'       => filled($donasi['nomor_rekening']) || $qris !== null,
                    'bank'           => $this->isi($donasi['bank']),
                    'nomor_rekening' => $this->isi($donasi['nomor_rekening']),
                    'atas_nama'      => $this->isi($donasi['atas_nama']),
                    'qris_url'       => $qris,
                    'catatan'        => 'MeowNesa tidak memproses pembayaran. Donasi ditransfer langsung ke rekening atau QRIS resmi Jaga Satwa UNESA di atas.',
                ],
                'pelaporan' => [
                    'form_url' => $this->url(config('jagasatwa.form_pelaporan_url')),
                    'catatan'  => 'Laporkan kucing liar yang butuh bantuan lewat Google Form resmi Jaga Satwa UNESA.',
                ],
                'kontak' => [
                    'whatsapp'         => $nomorWa,
                    'whatsapp_tautan'  => $nomorWa ? 'https://wa.me/'.$nomorWa : null,
                    'instagram'        => $instagram !== '' ? $instagram : null,
                    'instagram_tautan' => $instagram !== '' ? 'https://instagram.com/'.$instagram : null,
                ],
            ],
        ]);
    }

    private function isi(mixed $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }

    /** URL absolut apa adanya; path relatif (mis. gambar QRIS di folder public) dijadikan URL absolut. */
    private function url(mixed $nilai): ?string
    {
        $nilai = $this->isi($nilai);

        return $nilai === null ? null : (preg_match('#^https?://#i', $nilai) ? $nilai : asset(ltrim($nilai, '/')));
    }
}

<?php

namespace App\Services;

use App\Mail\NotifikasiMail;
use App\Models\Notifikasi;
use App\Models\PemberianMakan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Pengiriman notifikasi (dev-backend-11).
 *
 *  - Email: dikirim sistem (perintah terjadwal `notifikasi:kirim` atau tombol admin). Hasilnya TERKIRIM
 *    atau GAGAL (dengan alasan singkat dan jumlah percobaan) dan bisa dicoba ulang oleh admin.
 *  - WhatsApp: dikirim manual oleh admin lewat tautan `wa.me` ({@see WhatsAppNotificationService}); admin
 *    lalu menandai hasilnya di sini.
 *
 * Begitu terkirim, token aktivasi akun pada isi pesan disamarkan: tautan sekali pakai itu tidak perlu
 * tersimpan di basis data setelah sampai ke adopter.
 */
class PengirimanNotifikasiService
{
    public const BATAS_BATCH = 100;

    /** Kirim satu notifikasi email. Tidak melempar galat: kegagalan dicatat sebagai GAGAL. */
    public function kirimEmail(Notifikasi $n): Notifikasi
    {
        $n->percobaan = (int) $n->percobaan + 1;

        try {
            $email = $n->pengajuanAdopsi?->email ?? $n->user?->email;
            if (! $email) {
                throw new RuntimeException('Alamat email penerima tidak tersedia.');
            }

            Mail::to($email)->send(new NotifikasiMail($n->jenis_notifikasi, $n->isi_pesan));

            $this->tandaiTerkirim($n);
        } catch (Throwable $e) {
            report($e);

            $n->status_pengiriman = Notifikasi::STATUS_GAGAL;
            $n->catatan_galat = Str::limit($e->getMessage() !== '' ? $e->getMessage() : class_basename($e), 250, '');
        }

        $n->save();

        return $n;
    }

    /**
     * Kirim semua email yang masih PENDING (urut terlama dulu, paling banyak {@see BATAS_BATCH} per proses).
     *
     * @return array{diproses: int, terkirim: int, gagal: int}
     */
    public function kirimTertunda(int $batas = self::BATAS_BATCH): array
    {
        $hasil = ['diproses' => 0, 'terkirim' => 0, 'gagal' => 0];

        Notifikasi::with(['pengajuanAdopsi', 'user'])
            ->where('kanal', Notifikasi::KANAL_EMAIL)
            ->where('status_pengiriman', Notifikasi::STATUS_PENDING)
            ->orderBy('id_notifikasi')
            ->limit($batas)
            ->get()
            ->each(function (Notifikasi $n) use (&$hasil) {
                $this->kirimEmail($n);
                $hasil['diproses']++;
                $hasil[$n->status_pengiriman === Notifikasi::STATUS_TERKIRIM ? 'terkirim' : 'gagal']++;
            });

        return $hasil;
    }

    /** Admin melaporkan hasil pengiriman WhatsApp manual: TERKIRIM atau GAGAL (mis. nomor tidak aktif). */
    public function tandaiWhatsApp(Notifikasi $n, string $status, ?string $catatan = null): Notifikasi
    {
        $n->percobaan = (int) $n->percobaan + 1;

        if ($status === Notifikasi::STATUS_TERKIRIM) {
            $this->tandaiTerkirim($n);
        } else {
            $n->status_pengiriman = Notifikasi::STATUS_GAGAL;
            $n->catatan_galat = $catatan !== null && trim($catatan) !== '' ? trim($catatan) : 'Ditandai gagal oleh admin.';
        }
        $n->save();

        return $n;
    }

    /** Samarkan nilai token pada tautan aktivasi (`...token=<nilai>`). */
    public function sembunyikanToken(string $pesan): string
    {
        return preg_replace('/(token=)[^\s&]+/i', '$1[disamarkan]', $pesan) ?? $pesan;
    }

    private function tandaiTerkirim(Notifikasi $n): void
    {
        $n->status_pengiriman = Notifikasi::STATUS_TERKIRIM;
        $n->catatan_galat = null;
        $n->tanggal_dikirim = Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString();
        $n->isi_pesan = $this->sembunyikanToken($n->isi_pesan);
    }
}

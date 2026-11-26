<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\PengajuanAdopsi;

/**
 * Membuat catatan notifikasi untuk calon adopter (MEOW-F-29, F-30). Pengiriman nyata lewat email dan
 * WhatsApp dilakukan modul dev-backend-11; di sini hanya antrean `PENDING` per kanal.
 *
 * Calon adopter belum punya akun, sehingga id_user kosong dan penerima ditentukan dari data pengajuan
 * (email / nomor WhatsApp). Isi pesan tidak memuat alasan keputusan atau data internal apa pun.
 */
class NotifikasiPengajuanService
{
    public const KANAL = ['WhatsApp', 'Email'];

    public const JENIS_KONFIRMASI = 'Konfirmasi Pengajuan';
    public const JENIS_STATUS = 'Status Adopsi';
    public const JENIS_SERAH_TERIMA = 'Serah Terima';
    public const JENIS_AKTIVASI = 'Aktivasi Akun';

    /** Konfirmasi saat pengajuan diterima: memuat nomor dan kode tracking (satu-satunya cara memeriksa status). */
    public function konfirmasi(PengajuanAdopsi $p): void
    {
        $p->loadMissing('kucing');

        $this->antrekan($p, self::JENIS_KONFIRMASI, sprintf(
            'Halo %s, pengajuan adopsi untuk %s sudah kami terima. Nomor pengajuan: %s. Kode tracking: %s. '
            .'Simpan kode ini untuk memeriksa status kapan saja di %s',
            $this->namaDepan($p), $p->kucing->nama_kucing, $p->nomor_pengajuan, $p->kode_tracking, $this->tautan($p),
        ));
    }

    /** Pemberitahuan setiap perubahan status pengajuan. */
    public function statusBerubah(PengajuanAdopsi $p): void
    {
        $p->loadMissing('kucing');
        [$label, $keterangan] = PengajuanAdopsi::TEKS_PUBLIK[$p->status_pengajuan] ?? [$p->status_pengajuan, ''];

        $this->antrekan($p, self::JENIS_STATUS, sprintf(
            'Halo %s, status pengajuan adopsi %s (%s) kini: %s. %s Detail: %s',
            $this->namaDepan($p), $p->nomor_pengajuan, $p->kucing->nama_kucing, $label, $keterangan, $this->tautan($p),
        ));
    }

    /**
     * Serah terima dicatat. Bila akun adopter perlu diaktifkan, pesan memuat tautan aktivasi sekali pakai
     * (satu-satunya tempat token berada; API tidak pernah mengembalikannya ke admin).
     */
    public function serahTerima(PengajuanAdopsi $p, ?string $tautanAktivasi): void
    {
        $p->loadMissing('kucing');

        $this->antrekan($p, self::JENIS_SERAH_TERIMA, sprintf(
            'Halo %s, serah terima %s sudah dicatat. Terima kasih telah mengadopsi! %s '
            .'Pengurus Jaga Satwa akan mengingatkan Anda untuk memberi kabar kondisi kucing secara berkala.',
            $this->namaDepan($p), $p->kucing->nama_kucing,
            $tautanAktivasi
                ? "Akun MeowNesa Anda sudah dibuat. Atur password Anda di: {$tautanAktivasi} (berlaku 7 hari)."
                : 'Anda dapat masuk dengan akun MeowNesa yang sudah Anda miliki.',
        ));
    }

    /** Tautan aktivasi baru atas permintaan admin (tautan sebelumnya kedaluwarsa atau hilang). */
    public function aktivasiAkun(PengajuanAdopsi $p, string $tautanAktivasi): void
    {
        $this->antrekan($p, self::JENIS_AKTIVASI, sprintf(
            'Halo %s, ini tautan baru untuk mengatur password akun MeowNesa Anda: %s (berlaku 7 hari). Tautan sebelumnya tidak berlaku lagi.',
            $this->namaDepan($p), $tautanAktivasi,
        ));
    }

    /** Pengajuan lain untuk kucing yang sama ditutup otomatis setelah kucing diadopsi pemohon lain. */
    public function kucingSudahDiadopsi(PengajuanAdopsi $p): void
    {
        $p->loadMissing('kucing');

        $this->antrekan($p, self::JENIS_STATUS, sprintf(
            'Halo %s, mohon maaf, %s sudah diadopsi oleh pemohon lain sehingga pengajuan %s kami tutup. '
            .'Terima kasih atas minat Anda. Masih banyak kucing kampus yang menanti rumah: %s/kucing',
            $this->namaDepan($p), $p->kucing->nama_kucing, $p->nomor_pengajuan, rtrim((string) config('app.frontend_url'), '/'),
        ));
    }

    private function antrekan(PengajuanAdopsi $p, string $jenis, string $pesan): void
    {
        foreach (self::KANAL as $kanal) {
            Notifikasi::create([
                'id_pengajuan'      => $p->id_pengajuan,
                'id_user'           => null,
                'jenis_notifikasi'  => $jenis,
                'kanal'             => $kanal,
                'isi_pesan'         => $pesan,
                'status_pengiriman' => 'PENDING',
                'tanggal_dikirim'   => null,
            ]);
        }
    }

    private function namaDepan(PengajuanAdopsi $p): string
    {
        return explode(' ', trim($p->nama_pemohon))[0];
    }

    private function tautan(PengajuanAdopsi $p): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/adopsi/tracking/'.$p->kode_tracking;
    }
}

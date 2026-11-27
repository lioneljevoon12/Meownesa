<?php

namespace App\Services;

use App\Models\Kucing;
use App\Models\PengajuanAdopsi;
use App\Models\ScreeningAdopsi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mesin status pengajuan adopsi (MEOW-F-19, MEOW-BR-05, MEOW-NF-14). Keputusan selalu di tangan Admin
 * Jaga Satwa; layanan ini menjaga agar status pengajuan dan status kucing tidak saling bertentangan.
 *
 *   MENUNGGU --> SCREENING --> DISETUJUI --> (serah terima, modul 09) SELESAI
 *       \            \            \--> SCREENING (koreksi) / DITOLAK (batal)
 *        \--> DITOLAK \--> DITOLAK
 *
 * Efek ke kucing:
 *   - masuk SCREENING dari MENUNGGU : kucing Tersedia  -> Proses Screening (dikunci untuk pengajuan ini)
 *   - DITOLAK setelah memegang kucing: Proses Screening -> Tersedia (kucing kembali bisa diajukan)
 *   - DISETUJUI                     : kucing tetap Proses Screening sampai serah terima dicatat (BR-07)
 */
class PengajuanStatusService
{
    public function __construct(private readonly NotifikasiPengajuanService $notifikasi)
    {
    }

    public function ubah(int $idPengajuan, string $ke, User $admin): PengajuanAdopsi
    {
        return DB::transaction(function () use ($idPengajuan, $ke, $admin) {
            $awal = PengajuanAdopsi::findOrFail($idPengajuan);
            // Kunci kucing lebih dulu, baru pengajuan: urutan sama di semua jalur agar tidak saling tunggu.
            $kucing = Kucing::whereKey($awal->id_kucing)->lockForUpdate()->firstOrFail();
            $pengajuan = PengajuanAdopsi::whereKey($idPengajuan)->lockForUpdate()->firstOrFail();
            $dari = $pengajuan->status_pengajuan;

            if ($dari === $ke) {
                $this->tolak("Pengajuan sudah berstatus {$ke}.");
            }
            if (! in_array($ke, PengajuanAdopsi::TRANSISI[$dari] ?? [], true)) {
                $this->tolak("Status tidak dapat diubah dari {$dari} ke {$ke}.");
            }

            $statusKucingBaru = null;

            if ($ke === 'SCREENING' && $dari === 'MENUNGGU') {
                if (! in_array($kucing->status_kucing, ['Tersedia', 'Proses Screening'], true)) {
                    $this->tolak("Kucing berstatus {$kucing->status_kucing}, sehingga pengajuan ini tidak dapat diperiksa.");
                }
                $dipegangLain = PengajuanAdopsi::where('id_kucing', $kucing->id_kucing)
                    ->whereIn('status_pengajuan', PengajuanAdopsi::STATUS_MEMEGANG_KUCING)
                    ->whereKeyNot($pengajuan->id_pengajuan)
                    ->exists();
                if ($dipegangLain) {
                    $this->tolak('Kucing ini sedang diproses untuk pengajuan lain. Selesaikan atau tolak pengajuan tersebut terlebih dahulu.');
                }
                $statusKucingBaru = 'Proses Screening';
            }

            if ($ke === 'DISETUJUI') {
                $screening = ScreeningAdopsi::where('id_pengajuan', $pengajuan->id_pengajuan)->first();
                if (! $screening) {
                    $this->tolak('Isi hasil pemeriksaan (screening) terlebih dahulu sebelum menyetujui pengajuan.');
                }
                if ($screening->hasil_screening === 'Tidak Lolos') {
                    $this->tolak('Hasil pemeriksaan berstatus Tidak Lolos. Ubah hasil pemeriksaan terlebih dahulu bila ingin menyetujui.');
                }
            }

            if ($ke === 'DITOLAK' && in_array($dari, PengajuanAdopsi::STATUS_MEMEGANG_KUCING, true) && $kucing->status_kucing === 'Proses Screening') {
                $statusKucingBaru = 'Tersedia';
            }

            $pengajuan->update(['status_pengajuan' => $ke]);

            if ($statusKucingBaru !== null) {
                $kucing->update(['status_kucing' => $statusKucingBaru]);
                AuditLogger::record($admin, 'SINKRON_STATUS_KUCING', 'kucing', $kucing->id_kucing);
            }

            $this->notifikasi->statusBerubah($pengajuan);

            return $pengajuan;
        });
    }

    /**
     * Pelamar menarik pengajuannya sendiri (lewat kode tracking + nomor pengajuan). Boleh selama belum SELESAI/DITOLAK.
     * Bila pengajuan sedang memegang kucing (SCREENING/DISETUJUI), kucing dikembalikan ke Tersedia agar bisa diajukan lagi.
     */
    public function tarikOlehPelamar(int $idPengajuan): PengajuanAdopsi
    {
        return DB::transaction(function () use ($idPengajuan) {
            $awal = PengajuanAdopsi::findOrFail($idPengajuan);
            // Urutan kunci sama dengan jalur lain: kucing dulu, baru pengajuan.
            $kucing = Kucing::whereKey($awal->id_kucing)->lockForUpdate()->firstOrFail();
            $pengajuan = PengajuanAdopsi::whereKey($idPengajuan)->lockForUpdate()->firstOrFail();
            $dari = $pengajuan->status_pengajuan;

            if (! in_array($dari, ['MENUNGGU', 'SCREENING', 'DISETUJUI'], true)) {
                $this->tolak("Pengajuan yang berstatus {$dari} tidak dapat ditarik.");
            }

            $pengajuan->update(['status_pengajuan' => 'DIBATALKAN']);

            if (in_array($dari, PengajuanAdopsi::STATUS_MEMEGANG_KUCING, true) && $kucing->status_kucing === 'Proses Screening') {
                $kucing->update(['status_kucing' => 'Tersedia']);
                AuditLogger::record(null, 'SINKRON_STATUS_KUCING', 'kucing', $kucing->id_kucing);
            }

            AuditLogger::record(null, 'TARIK_PENGAJUAN', 'pengajuan_adopsi', $pengajuan->id_pengajuan);
            $this->notifikasi->statusBerubah($pengajuan);

            return $pengajuan;
        });
    }

    private function tolak(string $pesan): never
    {
        throw ValidationException::withMessages(['status' => $pesan]);
    }
}

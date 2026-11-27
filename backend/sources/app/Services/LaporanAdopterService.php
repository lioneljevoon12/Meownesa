<?php

namespace App\Services;

use App\Models\LaporanAdopter;
use App\Models\PascaAdopsi;
use App\Models\PemberianMakan;
use App\Models\SerahTerima;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Laporan kondisi kucing dari adopter (dev-backend-17, MEOW-F-26 s/d F-28, MEOW-BR-13).
 *
 * Adopter mengirim kabar sendiri lewat aplikasi (bukan lagi hanya lewat WhatsApp), tetapi yang mencatat ke riwayat
 * pemantauan tetap admin: laporan masuk ke kotak masuk berstatus MENUNGGU, lalu admin MENCATAT (menjadi baris
 * pasca_adopsi) atau MENOLAK dengan alasan. Dengan begitu aturan BR-13 tetap berlaku dan data pemantauan terkendali.
 */
class LaporanAdopterService
{
    public function __construct(private readonly PemantauanPascaAdopsiService $pemantauan)
    {
    }

    /**
     * Simpan laporan baru dari adopter pemilik adopsi ini. Ditolak bila pemantauan sudah berakhir (kucing tercatat
     * meninggal) atau sudah ada {@see LaporanAdopter::MAKS_MENUNGGU} laporan yang belum ditinjau.
     *
     * @param  array{status_kondisi: string, catatan?: string|null}  $data
     */
    public function kirim(SerahTerima $serahTerima, User $adopter, array $data): LaporanAdopter
    {
        return DB::transaction(function () use ($serahTerima, $adopter, $data) {
            // Kunci baris serah terima: dua laporan bersamaan tidak bisa sama-sama lolos batas jumlah.
            SerahTerima::whereKey($serahTerima->id_serah_terima)->lockForUpdate()->firstOrFail();

            if (($this->pemantauan->untuk($serahTerima->id_serah_terima)['status_pemantauan'] ?? null) === 'berakhir') {
                $this->tolak('status_kondisi', 'Pemantauan kucing ini sudah berakhir, sehingga laporan baru tidak diterima.');
            }

            $menunggu = LaporanAdopter::where('id_serah_terima', $serahTerima->id_serah_terima)->where('status_laporan', LaporanAdopter::MENUNGGU)->count();
            if ($menunggu >= LaporanAdopter::MAKS_MENUNGGU) {
                $this->tolak('status_kondisi', 'Masih ada '.LaporanAdopter::MAKS_MENUNGGU.' laporan Anda yang belum ditinjau pengurus. Tunggu sampai ditinjau sebelum mengirim yang baru.');
            }

            return LaporanAdopter::create([
                'id_serah_terima' => $serahTerima->id_serah_terima,
                'id_user'         => $adopter->id_user,
                'tanggal_laporan' => Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString(),
                'status_kondisi'  => $data['status_kondisi'],
                'catatan'         => $data['catatan'] ?? null,
                'status_laporan'  => LaporanAdopter::MENUNGGU,
            ]);
        });
    }

    /**
     * Admin mencatat laporan sebagai pembaruan pemantauan resmi (tanggal = tanggal laporan dikirim).
     *
     * @return array{0: LaporanAdopter, 1: PascaAdopsi}
     */
    public function catat(int $idLaporan, User $admin, ?string $catatanAdmin): array
    {
        return DB::transaction(function () use ($idLaporan, $admin, $catatanAdmin) {
            $laporan = LaporanAdopter::whereKey($idLaporan)->lockForUpdate()->firstOrFail();
            $this->pastikanMenunggu($laporan);

            $serahTerima = SerahTerima::findOrFail($laporan->id_serah_terima);
            $terakhir = PascaAdopsi::where('id_serah_terima', $laporan->id_serah_terima)
                ->orderByDesc('tanggal_update')->orderByDesc('id_pasca_adopsi')->value('status_kondisi');
            if (in_array($terakhir, PascaAdopsi::STATUS_AKHIR, true)) {
                $this->tolak('status_laporan', "Pemantauan sudah berakhir (kondisi terakhir: {$terakhir}). Tolak laporan ini, atau koreksi catatan terakhir bila itu salah catat.");
            }

            $pasca = PascaAdopsi::create([
                'id_serah_terima' => $laporan->id_serah_terima,
                // Tidak boleh sebelum hari serah terima (aturan pemantauan); laporan selalu dikirim sesudahnya, ini hanya pengaman.
                'tanggal_update'  => max($laporan->tanggal_laporan->format('Y-m-d'), $serahTerima->tanggal_serah_terima->format('Y-m-d')),
                'status_kondisi'  => $laporan->status_kondisi,
                'catatan'         => $laporan->catatan,
            ]);

            $this->tinjau($laporan, $admin, LaporanAdopter::DICATAT, $catatanAdmin, $pasca->id_pasca_adopsi);

            return [$laporan->refresh(), $pasca];
        });
    }

    public function tolakLaporan(int $idLaporan, User $admin, string $alasan): LaporanAdopter
    {
        return DB::transaction(function () use ($idLaporan, $admin, $alasan) {
            $laporan = LaporanAdopter::whereKey($idLaporan)->lockForUpdate()->firstOrFail();
            $this->pastikanMenunggu($laporan);
            $this->tinjau($laporan, $admin, LaporanAdopter::DITOLAK, $alasan, null);

            return $laporan->refresh();
        });
    }

    private function pastikanMenunggu(LaporanAdopter $laporan): void
    {
        if ($laporan->status_laporan !== LaporanAdopter::MENUNGGU) {
            $this->tolak('status_laporan', "Laporan ini sudah ditinjau (status: {$laporan->status_laporan}).");
        }
    }

    private function tinjau(LaporanAdopter $laporan, User $admin, string $status, ?string $catatanAdmin, ?int $idPasca): void
    {
        $laporan->update([
            'status_laporan'  => $status,
            'id_pasca_adopsi' => $idPasca,
            'catatan_admin'   => $catatanAdmin !== null && trim($catatanAdmin) !== '' ? trim($catatanAdmin) : null,
            'ditinjau_oleh'   => $admin->id_user,
            'ditinjau_pada'   => now(),
        ]);
    }

    private function tolak(string $field, string $pesan): never
    {
        throw ValidationException::withMessages([$field => $pesan]);
    }
}

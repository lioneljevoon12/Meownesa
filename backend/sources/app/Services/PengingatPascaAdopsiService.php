<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\PemberianMakan;
use App\Models\SerahTerima;
use Carbon\Carbon;

/**
 * Pengingat berkala kepada adopter untuk memberi kabar kondisi kucing (MEOW-F-31, MEOW-BR-12, BR-13).
 *
 * Aturan:
 *   - Adopsi yang masih dipantau (`aktif`/`terlambat`, lihat {@see PemantauanPascaAdopsiService}) diingatkan
 *     sejak hari jatuh tempo pembaruan (pembaruan terakhir + 7 hari) dan diulang tiap 7 hari selama belum
 *     memberi kabar, paling banyak {@see MAKS_PENGINGAT} kali per siklus. Setelahnya admin perlu
 *     menghubungi adopter langsung.
 *   - Setiap pengingat punya kunci unik (adopsi + jatuh tempo + minggu ke-n), jadi menjalankan proses ini
 *     berkali-kali pada hari yang sama tidak membuat pesan ganda. Begitu admin mencatat kabar baru, jatuh
 *     tempo bergeser dan siklus pengingat baru dimulai.
 *   - Dibuat untuk kedua kanal (WhatsApp manual + Email) berstatus PENDING; pengirimannya oleh
 *     {@see PengirimanNotifikasiService}. Pesan satu arah, tanpa percakapan.
 */
class PengingatPascaAdopsiService
{
    public const MAKS_PENGINGAT = 4;
    public const HARI_ANTAR_PENGINGAT = 7;

    public function __construct(private readonly PemantauanPascaAdopsiService $pemantauan)
    {
    }

    /**
     * @return array{adopsi_ditinjau: int, pengingat_dibuat: int, sudah_ada: int, dihentikan: int}
     */
    public function jalankan(?Carbon $sekarang = null): array
    {
        $hariIni = ($sekarang ?? Carbon::now(PemberianMakan::ZONA_WAKTU))->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->startOfDay();
        $hasil = ['adopsi_ditinjau' => 0, 'pengingat_dibuat' => 0, 'sudah_ada' => 0, 'dihentikan' => 0];

        $baris = $this->pemantauan->semua($hariIni)
            ->filter(fn (array $r) => in_array($r['status_pemantauan'], ['aktif', 'terlambat'], true));

        // Adopter yang sudah mengirim laporan (belum ditinjau admin) tidak ditagih kabar lagi.
        $sudahLapor = \App\Models\LaporanAdopter::where('status_laporan', \App\Models\LaporanAdopter::MENUNGGU)->pluck('id_serah_terima')->flip();
        $baris = $baris->reject(fn (array $r) => $sudahLapor->has($r['id_serah_terima']));

        $idPengajuan = SerahTerima::whereIn('id_serah_terima', $baris->pluck('id_serah_terima'))
            ->pluck('id_pengajuan', 'id_serah_terima');

        foreach ($baris as $r) {
            $jatuhTempo = Carbon::createFromFormat('Y-m-d', $r['jatuh_tempo'], PemberianMakan::ZONA_WAKTU)->startOfDay();
            if ($hariIni->lt($jatuhTempo) || ! isset($idPengajuan[$r['id_serah_terima']])) {
                continue;   // belum waktunya
            }

            $hasil['adopsi_ditinjau']++;
            $ke = intdiv((int) $jatuhTempo->diffInDays($hariIni), self::HARI_ANTAR_PENGINGAT);

            if ($ke >= self::MAKS_PENGINGAT) {
                $hasil['dihentikan']++;

                continue;
            }

            $pesan = $this->pesan($r);
            foreach (Notifikasi::KANAL as $kanal) {
                $n = Notifikasi::firstOrCreate(
                    ['kunci' => "pengingat:{$r['id_serah_terima']}:{$r['jatuh_tempo']}:{$ke}", 'kanal' => $kanal],
                    [
                        'id_pengajuan'      => $idPengajuan[$r['id_serah_terima']],
                        'jenis_notifikasi'  => Notifikasi::JENIS_REMINDER,
                        'isi_pesan'         => $pesan,
                        'status_pengiriman' => Notifikasi::STATUS_PENDING,
                    ],
                );

                $hasil[$n->wasRecentlyCreated ? 'pengingat_dibuat' : 'sudah_ada']++;
            }
        }

        return $hasil;
    }

    /** @param array<string, mixed> $r ringkasan dari PemantauanPascaAdopsiService */
    private function pesan(array $r): string
    {
        $nama = explode(' ', trim((string) ($r['adopter']['nama'] ?? 'Adopter')))[0];
        $kucing = $r['kucing']['nama_kucing'] ?? 'kucing Anda';
        $terakhir = $r['update_terakhir']['tanggal_update'] ?? null;

        $kabar = $terakhir
            ? "Kabar terakhir yang kami terima tertanggal {$terakhir}."
            : 'Kami belum menerima kabar sejak serah terima.';
        $waktu = $r['terlambat_hari'] > 0
            ? "sudah terlambat {$r['terlambat_hari']} hari"
            : 'jatuh tempo hari ini';

        return "Halo {$nama}, jadwal kabar kondisi {$kucing} {$waktu}. {$kabar} "
            .'Mohon kirimkan kabar singkat (nafsu makan, aktivitas, kesehatan) kepada pengurus Jaga Satwa lewat WhatsApp. '
            .'Terima kasih sudah merawat anabul dengan baik! Pesan ini pengingat satu arah.';
    }
}

<?php

namespace App\Services;

use App\Models\PascaAdopsi;
use App\Models\PemberianMakan;
use App\Models\SerahTerima;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Jadwal dan status pemantauan kucing setelah diadopsi (MEOW-F-26 s/d F-28, MEOW-BR-13).
 *
 * Aturan (semua tanggal menurut WIB):
 *   - Adopter diharapkan memberi kabar tiap {@see PascaAdopsi::INTERVAL_HARI} hari. Jatuh tempo berikutnya =
 *     tanggal pembaruan terakhir (atau tanggal serah terima bila belum ada pembaruan) + 7 hari.
 *   - Masa pemantauan {@see PascaAdopsi::MASA_BULAN} bulan sejak serah terima.
 *   - status_pemantauan: `berakhir` (kucing Meninggal) > `selesai` (masa pemantauan lewat) >
 *     `terlambat` (lewat jatuh tempo) > `aktif`.
 *
 * Modul pengingat (dev-backend-11) memakai {@see terlambat()} untuk menentukan siapa yang perlu diingatkan.
 */
class PemantauanPascaAdopsiService
{
    public const STATUS_PEMANTAUAN = ['aktif', 'terlambat', 'selesai', 'berakhir'];

    /** Semua adopsi beserta status pemantauannya (satu kueri; jumlah adopsi kecil). @return Collection<int, array<string, mixed>> */
    public function semua(?Carbon $hariIni = null): Collection
    {
        $hariIni = $this->hariIni($hariIni);

        return $this->kueri()->get()->map(fn (SerahTerima $st) => $this->ringkas($st, $hariIni));
    }

    /** Ringkasan satu adopsi, atau null bila tidak ada. @return array<string, mixed>|null */
    public function untuk(int $idSerahTerima, ?Carbon $hariIni = null): ?array
    {
        $st = $this->kueri()->whereKey($idSerahTerima)->first();

        return $st ? $this->ringkas($st, $this->hariIni($hariIni)) : null;
    }

    /** Adopsi yang pembaruannya sudah lewat jatuh tempo (bahan pengingat WhatsApp/email). @return Collection<int, array<string, mixed>> */
    public function terlambat(?Carbon $hariIni = null): Collection
    {
        return $this->semua($hariIni)->where('status_pemantauan', 'terlambat')->values();
    }

    private function hariIni(?Carbon $hariIni): Carbon
    {
        return ($hariIni ?? Carbon::now(PemberianMakan::ZONA_WAKTU))->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->startOfDay();
    }

    private function kueri()
    {
        $terakhir = fn (string $kolom) => PascaAdopsi::select($kolom)
            ->whereColumn('pasca_adopsi.id_serah_terima', 'serah_terima.id_serah_terima')
            ->orderByDesc('tanggal_update')->orderByDesc('id_pasca_adopsi')->limit(1);

        return SerahTerima::query()
            ->select('serah_terima.*')
            ->withCount('pascaAdopsi')
            ->addSelect([
                'tanggal_terakhir'  => $terakhir('tanggal_update'),
                'status_terakhir'   => $terakhir('status_kondisi'),
                'id_pasca_terakhir' => $terakhir('id_pasca_adopsi'),
            ])
            ->with(['pengajuanAdopsi.kucing', 'pengajuanAdopsi.adopter']);
    }

    /** @return array<string, mixed> */
    private function ringkas(SerahTerima $st, Carbon $hariIni): array
    {
        $tanggal = fn (?string $v) => $v ? Carbon::createFromFormat('Y-m-d', substr($v, 0, 10), PemberianMakan::ZONA_WAKTU)->startOfDay() : null;

        $mulai = $tanggal($st->tanggal_serah_terima->format('Y-m-d'));
        $akhirMasa = $mulai->copy()->addMonths(PascaAdopsi::MASA_BULAN);
        $terakhir = $tanggal($st->tanggal_terakhir);
        $jatuhTempo = ($terakhir ?? $mulai)->copy()->addDays(PascaAdopsi::INTERVAL_HARI);

        $status = match (true) {
            in_array($st->status_terakhir, PascaAdopsi::STATUS_AKHIR, true) => 'berakhir',
            $hariIni->gt($akhirMasa)                                        => 'selesai',
            $hariIni->gt($jatuhTempo)                                       => 'terlambat',
            default                                                         => 'aktif',
        };

        $p = $st->pengajuanAdopsi;

        return [
            'id_serah_terima'      => $st->id_serah_terima,
            'kucing'               => $p?->kucing ? [
                'id_kucing'   => $p->kucing->id_kucing,
                'kode_kucing' => $p->kucing->kode_kucing,
                'nama_kucing' => $p->kucing->nama_kucing,
            ] : null,
            // Kontak untuk menghubungi adopter (BR-13: kabar dikirim lewat WhatsApp/CP). Hanya untuk admin.
            'adopter'              => $p ? [
                'nama'           => $p->nama_pemohon,
                'nomor_whatsapp' => $p->nomor_whatsapp,
                'email'          => $p->email,
            ] : null,
            'tanggal_serah_terima' => $mulai->toDateString(),
            'jumlah_update'        => (int) $st->pasca_adopsi_count,
            'update_terakhir'      => $terakhir ? [
                'id_pasca_adopsi' => (int) $st->id_pasca_terakhir,
                'tanggal_update'  => $terakhir->toDateString(),
                'status_kondisi'  => $st->status_terakhir,
            ] : null,
            'kondisi_terkini'      => $st->status_terakhir,
            'jatuh_tempo'          => $jatuhTempo->toDateString(),
            'terlambat_hari'       => $status === 'terlambat' ? (int) $jatuhTempo->diffInDays($hariIni) : 0,
            'masa_pemantauan_sampai' => $akhirMasa->toDateString(),
            'status_pemantauan'    => $status,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\PemberianMakan;
use App\Models\PengajuanAdopsi;
use Carbon\Carbon;

/**
 * Pembuat identitas pengajuan adopsi (MEOW-BR-04).
 *
 * - nomor_pengajuan: berurutan per tahun, mis. ADP-2026-001 (mudah dibaca/disebut lewat WhatsApp).
 * - kode_tracking: acak 12 karakter dari alfabet tanpa karakter membingungkan (tanpa I, O, 0, 1),
 *   mis. MN-7K2Q-9XPA-4HDM. Berentropi ~60 bit sehingga tidak dapat ditebak; inilah "rahasia" yang
 *   dipegang calon adopter, jadi dibuat dengan random_int (CSPRNG), bukan dari nomor urut.
 *
 * Keunikan dijamin constraint UNIQUE di basis data; pemanggil mengulang bila terjadi tabrakan.
 */
class TrackingCodeGenerator
{
    private const ALFABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function nomorPengajuan(?Carbon $sekarang = null): string
    {
        $tahun = ($sekarang ?? Carbon::now(PemberianMakan::ZONA_WAKTU))->format('Y');
        $awalan = "ADP-{$tahun}-";

        $terbesar = PengajuanAdopsi::where('nomor_pengajuan', 'like', $awalan.'%')
            ->pluck('nomor_pengajuan')
            ->map(fn (string $nomor) => (int) substr($nomor, strlen($awalan)))
            ->max() ?? 0;

        return $awalan.str_pad((string) ($terbesar + 1), 3, '0', STR_PAD_LEFT);
    }

    public function kodeTracking(): string
    {
        $acak = '';
        for ($i = 0; $i < 12; $i++) {
            $acak .= self::ALFABET[random_int(0, strlen(self::ALFABET) - 1)];
        }

        return 'MN-'.implode('-', str_split($acak, 4));
    }
}

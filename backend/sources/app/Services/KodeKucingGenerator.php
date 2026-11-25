<?php

namespace App\Services;

use App\Models\Kucing;
use App\Models\Lokasi;

/**
 * Membuat nomor kalung berikutnya per kampus, mis. KTT-081 (Ketintang) atau LWT-019 (Lidah Wetan).
 * Kode tetap unik di tingkat database; pemanggil mengulang bila terjadi tabrakan balapan.
 */
class KodeKucingGenerator
{
    public function untuk(Lokasi $lokasi): string
    {
        $prefix = Kucing::PREFIX_KODE[$lokasi->kampus->nama_kampus] ?? 'KCG';

        $terbesar = Kucing::where('kode_kucing', 'like', $prefix.'-%')
            ->pluck('kode_kucing')
            ->map(fn (string $kode) => (int) substr($kode, strlen($prefix) + 1))
            ->max() ?? 0;

        return sprintf('%s-%03d', $prefix, $terbesar + 1);
    }
}

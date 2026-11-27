<?php

namespace App\Services;

use App\Http\Resources\KampusResource;
use App\Http\Resources\KucingAdminResource;
use App\Http\Resources\LokasiResource;
use App\Http\Resources\PemberianMakanResource;
use App\Http\Resources\RiwayatKesehatanAdminResource;
use App\Models\Kampus;
use App\Models\Kucing;
use App\Models\Lokasi;
use App\Models\PemberianMakan;
use App\Models\RiwayatKesehatan;
use App\Models\SyncPenghapusan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Penarikan data untuk PWA yang bekerja offline (dev-backend-15).
 *
 * Model sinkronisasi:
 *   - TARIK (server -> HP): HP mengirim `sejak` (kursor dari penarikan sebelumnya) dan mendapat semua baris yang
 *     berubah sejak itu, plus daftar id yang dihapus. Tanpa `sejak` (atau bila terlalu lama) HP mendapat seluruh
 *     data (penarikan penuh) dan harus mengganti salinan lokalnya.
 *   - DORONG (HP -> server): hanya data tambah-saja (feeding, riwayat kesehatan, foto) yang boleh ditulis offline.
 *     Tiap catatan membawa `client_uuid` sehingga pengiriman ulang aman. Mengubah profil kucing/lokasi butuh
 *     koneksi (tidak ada penggabungan konflik): itu sengaja, karena relawan lapangan hampir selalu hanya menambah.
 *
 * Kursor: waktu server (UTC) dimundurkan beberapa detik agar baris yang baru di-commit tidak terlewat; baris yang
 * terbaca dua kali aman karena HP menggabungkan menurut id. Tiap koleksi dibatasi; bila terpotong, `lengkap` = false
 * dan kursor menunjuk titik terakhir yang sudah diterima, HP cukup menarik lagi.
 */
class SinkronService
{
    /** UTC dengan akhiran Z (tanpa tanda +, aman dipakai di query string). */
    public const FORMAT_WAKTU = 'Y-m-d\TH:i:s\Z';

    /**
     * @return array<string, mixed>
     */
    public function tarik(?Carbon $sejak, ?int $hariFeeding = null, ?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? Carbon::now())->copy()->utc();
        $hariFeeding ??= (int) config('sinkron.hari_feeding_bawaan');

        // Catatan penghapusan hanya disimpan sebentar: HP yang terlalu lama tidak sinkron tidak bisa dipercaya
        // untuk menghapus yang sudah hilang, jadi diberi penarikan penuh.
        $terlaluLama = $sejak && $sejak->lt($sekarang->copy()->subDays((int) config('sinkron.masa_penghapusan_hari')));
        $penuh = $sejak === null || $terlaluLama;
        $sejak = $penuh ? null : $sejak->copy()->utc();

        $terpotong = [];

        $kucing = $this->ambil(
            Kucing::query()
                ->with(['lokasi.kampus', 'fotoUtama', 'fotoKucing' => fn ($q) => $q->orderByDesc('is_utama')->orderBy('id_foto_kucing')])
                ->withCount('fotoKucing'),
            $sejak, 'id_kucing', $terpotong,
        );
        $lokasi = $this->ambil(Lokasi::query()->with('kampus'), $sejak, 'id_lokasi', $terpotong);
        $kesehatan = $this->ambil(RiwayatKesehatan::query(), $sejak, 'id_kesehatan', $terpotong);

        $feedingKueri = PemberianMakan::query()->with(['lokasi.kampus', 'kucing']);
        if ($penuh) {
            $feedingKueri->where('tanggal', '>=', $sekarang->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->subDays($hariFeeding)->toDateString());
        }
        $feeding = $this->ambil($feedingKueri, $sejak, 'id_pemberian', $terpotong);

        $lengkap = $terpotong === [];
        $kursor = $lengkap
            ? $sekarang->copy()->subSeconds((int) config('sinkron.tumpang_tindih_detik'))
            : min($terpotong);

        return [
            'waktu_server'   => $sekarang->format(self::FORMAT_WAKTU),
            'tanggal_server' => $sekarang->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->toDateString(),
            'penuh'          => $penuh,
            'lengkap'        => $lengkap,
            'kursor'         => $kursor->utc()->format(self::FORMAT_WAKTU),
            'kampus'         => KampusResource::collection(Kampus::orderBy('id_kampus')->get())->resolve(),
            'lokasi'         => LokasiResource::collection($lokasi)->resolve(),
            'kucing'         => KucingAdminResource::collection($kucing)->resolve(),
            'kesehatan'      => RiwayatKesehatanAdminResource::collection($kesehatan)->resolve(),
            'feeding'        => PemberianMakanResource::collection($feeding)->resolve(),
            'terhapus'       => $this->terhapus($sejak),
        ];
    }

    /** Hapus catatan penghapusan yang sudah melewati masa simpan. Mengembalikan jumlah baris terhapus. */
    public function bersihkan(?Carbon $sekarang = null): int
    {
        $batas = ($sekarang ?? Carbon::now())->copy()->subDays((int) config('sinkron.masa_penghapusan_hari'));

        return SyncPenghapusan::where('dihapus_pada', '<', $batas)->delete();
    }

    /**
     * Ambil baris yang berubah sejak $sejak (urut waktu ubah lalu id), paling banyak batas per koleksi.
     * Bila terpotong, waktu ubah baris terakhir yang disertakan dicatat di $terpotong sebagai kursor lanjutan.
     *
     * @param  array<int, Carbon>  $terpotong
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    private function ambil(Builder $kueri, ?Carbon $sejak, string $kunci, array &$terpotong): Collection
    {
        $batas = (int) config('sinkron.batas_per_koleksi');

        $baris = $kueri
            ->when($sejak, fn (Builder $q) => $q->where('updated_at', '>=', $sejak->copy()->setTimezone(config('app.timezone'))))
            ->orderBy('updated_at')->orderBy($kunci)
            ->limit($batas + 1)
            ->get();

        if ($baris->count() > $batas) {
            $baris = $baris->take($batas);
            $terpotong[] = Carbon::parse($baris->last()->updated_at)->utc();
        }

        return $baris;
    }

    /** @return array<string, array<int, int>> id yang dihapus sejak $sejak (kosong pada penarikan penuh) */
    private function terhapus(?Carbon $sejak): array
    {
        $peta = ['kucing' => 'kucing', 'lokasi' => 'lokasi', 'kesehatan' => 'riwayat_kesehatan', 'feeding' => 'pemberian_makan'];
        $hasil = array_fill_keys(array_keys($peta), []);

        if ($sejak === null) {
            return $hasil;
        }

        $baris = SyncPenghapusan::where('dihapus_pada', '>=', $sejak->copy()->setTimezone(config('app.timezone')))
            ->whereIn('entitas', array_values($peta))
            ->orderBy('id_penghapusan')->get(['entitas', 'id_data']);

        foreach ($peta as $kunci => $entitas) {
            $hasil[$kunci] = $baris->where('entitas', $entitas)->pluck('id_data')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        return $hasil;
    }
}

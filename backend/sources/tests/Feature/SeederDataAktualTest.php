<?php

namespace Tests\Feature;

use App\Models\FotoKucing;
use App\Models\Kucing;
use App\Models\Lokasi;
use Database\Seeders\LokasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Memastikan seeder data aktual Jaga Satwa konsisten dan aman ditampilkan. */
class SeederDataAktualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_jumlah_kampus_lokasi_dan_kucing_sesuai_data_aktual(): void
    {
        $this->assertDatabaseCount('kampus', 2);
        $this->assertSame(22, Lokasi::whereHas('kampus', fn ($q) => $q->where('nama_kampus', 'Kampus Ketintang'))->count());
        $this->assertSame(6, Lokasi::whereHas('kampus', fn ($q) => $q->where('nama_kampus', 'Kampus Lidah Wetan'))->count());
        $this->assertSame(98, Kucing::count());
        $this->assertSame(80, Kucing::where('kode_kucing', 'like', 'KTT-%')->count());
        $this->assertSame(18, Kucing::where('kode_kucing', 'like', 'LWT-%')->count());
    }

    public function test_nilai_status_dan_steril_hanya_dari_daftar_resmi(): void
    {
        $this->assertSame([], Kucing::whereNotIn('status_kucing', Kucing::STATUS)->pluck('kode_kucing')->all());
        $this->assertSame([], Kucing::whereNotIn('status_steril', Kucing::STATUS_STERIL)->pluck('kode_kucing')->all());
        $this->assertSame([], Kucing::whereNotIn('jenis_kelamin', ['Jantan', 'Betina'])->pluck('kode_kucing')->all());
    }

    public function test_setiap_foto_ada_filenya_dan_maksimal_satu_foto_utama(): void
    {
        $this->assertSame(67, FotoKucing::count());

        foreach (FotoKucing::pluck('url_foto') as $url) {
            $this->assertFileExists(public_path($url), "File foto tidak ditemukan: {$url}");
        }

        $this->assertSame(0, FotoKucing::where('is_utama', true)->groupBy('id_kucing')->havingRaw('COUNT(*) > 1')->count());
    }

    public function test_setiap_kucing_yang_punya_foto_punya_tepat_satu_foto_utama(): void
    {
        $tanpaUtama = Kucing::whereHas('fotoKucing')->whereDoesntHave('fotoKucing', fn ($q) => $q->where('is_utama', true))->count();

        $this->assertSame(0, $tanpaUtama);
        $this->assertSame(67, FotoKucing::where('is_utama', true)->count());   // 1 foto per kucing berfoto
    }

    public function test_deskripsi_publik_tidak_memuat_catatan_tempat_tinggal_orang(): void
    {
        $this->assertSame(0, Kucing::where('deskripsi', 'like', '%kos %')->orWhere('nama_kucing', 'like', '%(%')->count());
    }

    public function test_seeder_aman_dijalankan_ulang(): void
    {
        $this->seed();

        $this->assertSame(98, Kucing::count());
        $this->assertSame(67, FotoKucing::count());
        $this->assertSame(28, Lokasi::count());
    }

    // ------------------------------------------------------------ koordinat lokasi

    public function test_koordinat_mencakup_tepat_semua_lokasi_dan_berada_di_area_kampus(): void
    {
        $batas = [   // [lintang min, lintang maks, bujur min, bujur maks] kira-kira area kampus
            'Kampus Ketintang'   => [-7.3185, -7.3105, 112.7235, 112.7305],
            'Kampus Lidah Wetan' => [-7.2990, -7.3040, 112.6650, 112.6740],
        ];

        foreach (Lokasi::with('kampus')->get() as $lokasi) {
            $nama = $lokasi->kampus->nama_kampus;
            $titik = LokasiSeeder::koordinat()[$nama][$lokasi->nama_lokasi] ?? null;

            $this->assertNotNull($titik, "{$nama} / {$lokasi->nama_lokasi} belum punya koordinat.");
            $this->assertEqualsWithDelta($titik['lat'], $lokasi->koordinat_x, 1e-7);
            $this->assertEqualsWithDelta($titik['lng'], $lokasi->koordinat_y, 1e-7);
            $this->assertContains($titik['akurasi'], ['google_maps', 'osm', 'perkiraan']);

            [$latA, $latB, $lngA, $lngB] = $batas[$nama];
            $this->assertTrue(
                $lokasi->koordinat_x >= min($latA, $latB) && $lokasi->koordinat_x <= max($latA, $latB) && $lokasi->koordinat_y >= $lngA && $lokasi->koordinat_y <= $lngB,
                "{$lokasi->nama_lokasi} berada di luar area {$nama}.",
            );
        }

        // tidak ada entri koordinat yatim (nama lokasi salah ketik) dan tidak ada dua lokasi di titik yang sama
        $namaDiDb = Lokasi::with('kampus')->get()->map(fn ($l) => $l->kampus->nama_kampus.'|'.$l->nama_lokasi)->sort()->values()->all();
        $namaDiFile = collect(LokasiSeeder::koordinat())->flatMap(fn ($daftar, $kampus) => collect($daftar)->keys()->map(fn ($n) => "{$kampus}|{$n}"))->sort()->values()->all();
        $this->assertSame($namaDiDb, $namaDiFile);
        $this->assertSame(28, Lokasi::selectRaw('koordinat_x, koordinat_y')->distinct()->get()->count());
    }

    public function test_peta_publik_memetakan_semua_lokasi_hasil_seeder(): void
    {
        $this->getJson('/api/peta/persebaran')->assertOk()
            ->assertJsonPath('meta.total_titik', 28)->assertJsonPath('meta.tanpa_koordinat', 0);
    }

    public function test_seeder_ulang_memindahkan_titik_lama_tetapi_tidak_menimpa_koreksi_admin(): void
    {
        $lama = Lokasi::where('nama_lokasi', 'FEB')->firstOrFail();
        $diubahAdmin = Lokasi::where('nama_lokasi', 'FIP')->firstOrFail();
        $lama->update(['koordinat_x' => -7.3129, 'koordinat_y' => 112.7275]);          // titik tengah bawaan lama
        $diubahAdmin->update(['koordinat_x' => -7.30111, 'koordinat_y' => 112.67111]);  // sudah dikoreksi admin

        $this->seed(LokasiSeeder::class);

        $this->assertEqualsWithDelta(-7.3130393, $lama->refresh()->koordinat_x, 1e-7);
        $this->assertEqualsWithDelta(112.7283961, $lama->koordinat_y, 1e-7);
        $this->assertEqualsWithDelta(-7.30111, $diubahAdmin->refresh()->koordinat_x, 1e-7);
        $this->assertSame(28, Lokasi::count());
    }
}

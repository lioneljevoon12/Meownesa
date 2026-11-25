<?php

namespace Tests\Feature;

use App\Models\FotoKucing;
use App\Models\Kucing;
use App\Models\Lokasi;
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
}

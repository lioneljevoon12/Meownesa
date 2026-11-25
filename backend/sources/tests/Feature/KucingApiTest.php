<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatDataKucing;
use Tests\TestCase;

/** dev-backend-04: katalog kucing PUBLIK (MEOW-F-01, F-02, F-06, MEOW-NF-05). */
class KucingApiTest extends TestCase
{
    use MembuatDataKucing, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
    }

    private function nama(string $uri): array
    {
        return collect($this->getJson($uri)->assertOk()->json('data'))->pluck('nama_kucing')->all();
    }

    public function test_hanya_status_publik_yang_tampil(): void
    {
        $this->buatKucing(['nama_kucing' => 'Aman1', 'status_kucing' => 'Tersedia']);
        $this->buatKucing(['nama_kucing' => 'Aman2', 'status_kucing' => 'Terdata']);
        foreach (['Proses Screening', 'Diadopsi', 'Hilang', 'Almarhum'] as $i => $status) {
            $this->buatKucing(['nama_kucing' => 'Rahasia'.$i, 'status_kucing' => $status]);
        }

        $this->assertSame(['Aman1', 'Aman2'], $this->nama('/api/kucing'));
    }

    public function test_payload_publik_tidak_membocorkan_data_internal(): void
    {
        $k = $this->buatKucing(['status_kucing' => 'Tersedia']);
        $this->buatFoto($k, true);

        $item = $this->getJson('/api/kucing')->assertOk()->json('data.0');
        $this->assertEqualsCanonicalizing(
            ['id_kucing', 'kode_kucing', 'nama_kucing', 'jenis_kelamin', 'kelompok_usia', 'status_steril',
             'status_kucing', 'status_label', 'deskripsi', 'lokasi', 'foto_utama'],
            array_keys($item)
        );
        $this->assertSame('Siap Diadopsi', $item['status_label']);
        $this->assertSame('Kampus Ketintang', $item['lokasi']['nama_kampus']);
        $this->assertStringContainsString('/images/kucing/upload/uji-', $item['foto_utama']);   // URL absolut ke API
    }

    public function test_foto_utama_null_bila_belum_ada_foto(): void
    {
        $this->buatKucing();

        $this->getJson('/api/kucing')->assertOk()->assertJsonPath('data.0.foto_utama', null);
    }

    public function test_filter_kampus_lokasi_status_gender_usia_dan_steril(): void
    {
        $this->buatKucing(['nama_kucing' => 'A', 'id_lokasi' => $this->danau->id_lokasi, 'jenis_kelamin' => 'Jantan', 'status_kucing' => 'Tersedia', 'kelompok_usia' => 'Kitten', 'status_steril' => 'Sudah']);
        $this->buatKucing(['nama_kucing' => 'B', 'id_lokasi' => $this->fip->id_lokasi, 'jenis_kelamin' => 'Betina', 'status_kucing' => 'Terdata', 'kelompok_usia' => 'Dewasa', 'status_steril' => 'Belum']);

        $this->assertSame(['A'], $this->nama('/api/kucing?id_kampus='.$this->ktt->id_kampus));
        $this->assertSame(['B'], $this->nama('/api/kucing?id_lokasi='.$this->fip->id_lokasi));
        $this->assertSame(['A'], $this->nama('/api/kucing?status=Tersedia'));
        $this->assertSame(['B'], $this->nama('/api/kucing?jenis_kelamin=Betina'));
        $this->assertSame(['A'], $this->nama('/api/kucing?kelompok_usia=Kitten'));
        $this->assertSame(['B'], $this->nama('/api/kucing?status_steril=Belum'));
        $this->assertSame([], $this->nama('/api/kucing?id_kampus='.$this->ktt->id_kampus.'&jenis_kelamin=Betina'));
    }

    public function test_pencarian_nama_kode_ciri_dan_lokasi(): void
    {
        $this->buatKucing(['nama_kucing' => 'Oyen Barbar', 'kode_kucing' => 'KTT-901', 'deskripsi' => 'Ekor panjang', 'id_lokasi' => $this->danau->id_lokasi]);
        $this->buatKucing(['nama_kucing' => 'Mochi', 'kode_kucing' => 'LWT-902', 'deskripsi' => 'Belang tiga', 'id_lokasi' => $this->fip->id_lokasi]);

        $this->assertSame(['Oyen Barbar'], $this->nama('/api/kucing?q=oyen'));
        $this->assertSame(['Mochi'], $this->nama('/api/kucing?q=LWT-902'));
        $this->assertSame(['Mochi'], $this->nama('/api/kucing?q=belang'));
        $this->assertSame(['Oyen Barbar'], $this->nama('/api/kucing?q=danau'));
        $this->assertSame([], $this->nama('/api/kucing?q=tidak-ada-sama-sekali'));
    }

    public function test_filter_tidak_valid_422_termasuk_status_non_publik(): void
    {
        $this->getJson('/api/kucing?status=Almarhum')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->getJson('/api/kucing?status=Diadopsi')->assertStatus(422);
        $this->getJson('/api/kucing?id_kampus=999')->assertStatus(422);
        $this->getJson('/api/kucing?jenis_kelamin=Robot')->assertStatus(422);
        $this->getJson('/api/kucing?per_page=51')->assertStatus(422);
        $this->getJson('/api/kucing?page=0')->assertStatus(422);
    }

    public function test_paginasi_dan_urutan(): void
    {
        foreach (['Cici', 'Aaa', 'Bobo', 'Dudu', 'Eko'] as $nama) {
            $this->buatKucing(['nama_kucing' => $nama]);
        }

        $res = $this->getJson('/api/kucing?per_page=2&page=2')->assertOk()
            ->assertJsonPath('meta.halaman', 2)
            ->assertJsonPath('meta.halaman_akhir', 3)
            ->assertJsonPath('meta.per_halaman', 2)
            ->assertJsonPath('meta.total', 5);
        $this->assertSame(['Cici', 'Dudu'], collect($res->json('data'))->pluck('nama_kucing')->all());   // urut nama

        $this->assertSame('Eko', $this->nama('/api/kucing?sort=terbaru')[0]);
    }

    public function test_detail_memuat_galeri_dengan_foto_utama_di_depan(): void
    {
        $k = $this->buatKucing(['status_kucing' => 'Tersedia']);
        $this->buatFoto($k, false);
        $utama = $this->buatFoto($k, true);
        $this->buatFoto($k, false);

        $res = $this->getJson("/api/kucing/{$k->id_kucing}")->assertOk()
            ->assertJsonPath('data.nama_kucing', $k->nama_kucing)
            ->assertJsonCount(3, 'data.galeri');

        $this->assertTrue($res->json('data.galeri.0.is_utama'));
        $this->assertSame($utama->id_foto_kucing, $res->json('data.galeri.0.id_foto_kucing'));
        $this->assertSame(1, collect($res->json('data.galeri'))->where('is_utama', true)->count());
    }

    public function test_detail_kucing_non_publik_dan_tidak_ada_404(): void
    {
        $diadopsi = $this->buatKucing(['status_kucing' => 'Diadopsi']);
        $almarhum = $this->buatKucing(['status_kucing' => 'Almarhum']);

        $this->getJson("/api/kucing/{$diadopsi->id_kucing}")->assertStatus(404)->assertJsonPath('success', false);
        $this->getJson("/api/kucing/{$almarhum->id_kucing}")->assertStatus(404);
        $this->getJson('/api/kucing/99999')->assertStatus(404);
        $this->getJson('/api/kucing/abc')->assertStatus(404);
    }
}

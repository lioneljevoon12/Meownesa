<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RiwayatKesehatan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\TestCase;

/** dev-backend-05: riwayat kesehatan dan perawatan + sinkronisasi status steril. */
class KesehatanApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
    }

    private function catat($kucing, string $jenis, string $tanggal, ?string $catatan = null): RiwayatKesehatan
    {
        return RiwayatKesehatan::create(['id_kucing' => $kucing->id_kucing, 'jenis_tindakan' => $jenis, 'tanggal' => $tanggal, 'catatan' => $catatan]);
    }

    private function payload(array $o = []): array
    {
        return $o + ['jenis_tindakan' => 'Vaksinasi', 'tanggal' => '2026-10-01', 'catatan' => 'Tricat'];
    }

    // ---------------------------------------------------------------- publik

    public function test_riwayat_publik_urut_dari_tindakan_terbaru_dan_hanya_tiga_field(): void
    {
        $k = $this->buatKucing(['status_kucing' => 'Tersedia']);
        $this->catat($k, 'Sterilisasi', '2026-01-15', 'Klinik A');
        $this->catat($k, 'Vaksinasi', '2026-09-01');
        $this->catat($k, 'Pemberian Vitamin', '2026-09-01');   // tanggal sama: yang dibuat belakangan di atas
        $this->catat($k, 'Pemeriksaan', '2026-05-20');

        $res = $this->getJson("/api/kucing/{$k->id_kucing}/kesehatan")->assertOk();

        $this->assertSame(
            ['Pemberian Vitamin', 'Vaksinasi', 'Pemeriksaan', 'Sterilisasi'],
            collect($res->json('data'))->pluck('jenis_tindakan')->all()
        );
        $this->assertEqualsCanonicalizing(['tanggal', 'jenis_tindakan', 'catatan'], array_keys($res->json('data.0')));
        $this->assertSame('2026-09-01', $res->json('data.0.tanggal'));
    }

    public function test_riwayat_publik_kosong_200_dan_hanya_untuk_kucing_publik(): void
    {
        $biasa = $this->buatKucing(['status_kucing' => 'Terdata']);
        $this->getJson("/api/kucing/{$biasa->id_kucing}/kesehatan")->assertOk()->assertJsonCount(0, 'data');

        foreach (['Proses Screening', 'Diadopsi', 'Hilang', 'Almarhum'] as $status) {
            $k = $this->buatKucing(['status_kucing' => $status]);
            $this->catat($k, 'Pemeriksaan', '2026-01-01');
            $this->getJson("/api/kucing/{$k->id_kucing}/kesehatan")->assertStatus(404);
        }
        $this->getJson('/api/kucing/99999/kesehatan')->assertStatus(404);
        $this->getJson('/api/kucing/abc/kesehatan')->assertStatus(404);
    }

    // ------------------------------------------------------------ hak akses

    public function test_endpoint_kesehatan_admin_butuh_role_admin(): void
    {
        $k = $this->buatKucing();
        $r = $this->catat($k, 'Vaksinasi', '2026-01-01');
        $rute = [
            ['getJson', "/api/admin/kucing/{$k->id_kucing}/kesehatan"],
            ['postJson', "/api/admin/kucing/{$k->id_kucing}/kesehatan"],
            ['putJson', "/api/admin/kesehatan/{$r->id_kesehatan}"],
            ['deleteJson', "/api/admin/kesehatan/{$r->id_kesehatan}"],
        ];
        $panggil = fn (string $m, string $u, array $h = []) => $m === 'getJson' ? $this->getJson($u, $h) : $this->$m($u, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);
        }
        $this->assertDatabaseCount('riwayat_kesehatan', 1);
    }

    public function test_admin_melihat_riwayat_semua_status_dengan_id_dan_timestamp(): void
    {
        $k = $this->buatKucing(['status_kucing' => 'Almarhum']);
        $this->catat($k, 'Pemeriksaan', '2026-03-01');

        $res = $this->getJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", $this->asRole('admin'))->assertOk()->assertJsonCount(1, 'data');
        $this->assertEqualsCanonicalizing(
            ['id_kesehatan', 'id_kucing', 'tanggal', 'jenis_tindakan', 'catatan', 'created_at', 'updated_at'],
            array_keys($res->json('data.0'))
        );
        $this->getJson('/api/admin/kucing/99999/kesehatan', $this->asRole('admin'))->assertStatus(404);
    }

    // ---------------------------------------------------------------- tambah

    public function test_admin_menambah_catatan_dan_tercatat_di_audit(): void
    {
        $k = $this->buatKucing();

        $res = $this->postJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", $this->payload(), $this->asRole('admin'))
            ->assertCreated()
            ->assertJsonPath('data.jenis_tindakan', 'Vaksinasi')
            ->assertJsonPath('data.tanggal', '2026-10-01')
            ->assertJsonPath('data.id_kucing', $k->id_kucing);

        $this->assertDatabaseHas('audit_log', [
            'aktivitas' => 'CREATE', 'nama_tabel' => 'riwayat_kesehatan', 'id_data' => $res->json('data.id_kesehatan'),
        ]);
    }

    public function test_validasi_tambah_catatan(): void
    {
        $k = $this->buatKucing();
        $h = $this->asRole('admin');
        $uri = "/api/admin/kucing/{$k->id_kucing}/kesehatan";
        $besok = Carbon::now('Asia/Jakarta')->addDay()->toDateString();

        $this->postJson($uri, [], $h)->assertStatus(422)->assertJsonValidationErrors(['jenis_tindakan', 'tanggal']);

        foreach ([
            ['jenis_tindakan' => 'Operasi Plastik'],
            ['tanggal' => '01-10-2026'],
            ['tanggal' => 'kemarin'],
            ['tanggal' => '2010-01-01'],
            ['tanggal' => $besok],                                  // masa depan menurut WIB
            ['catatan' => str_repeat('a', 2001)],
            ['jenis_tindakan' => 'Lainnya', 'catatan' => '   '],      // "Lainnya" wajib dijelaskan
        ] as $salah) {
            $this->postJson($uri, $this->payload($salah), $h)->assertStatus(422);
        }

        $this->assertDatabaseCount('riwayat_kesehatan', 0);
        $this->assertSame(0, AuditLog::where('nama_tabel', 'riwayat_kesehatan')->count());
    }

    public function test_tanggal_hari_ini_menurut_wib_diterima(): void
    {
        $k = $this->buatKucing();
        $hariIniWib = Carbon::now('Asia/Jakarta')->toDateString();

        $this->postJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", $this->payload(['tanggal' => $hariIniWib]), $this->asRole('admin'))
            ->assertCreated();
        $this->postJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", ['jenis_tindakan' => 'Lainnya', 'tanggal' => $hariIniWib, 'catatan' => 'Dijelaskan'], $this->asRole('admin'))
            ->assertCreated();
    }

    public function test_tambah_catatan_untuk_kucing_tidak_ada_404_walau_isian_salah(): void
    {
        $this->postJson('/api/admin/kucing/99999/kesehatan', [], $this->asRole('admin'))->assertStatus(404);
    }

    // ------------------------------------------------- sinkronisasi steril

    public function test_mencatat_sterilisasi_otomatis_menetapkan_status_steril_sudah(): void
    {
        foreach (['Belum', 'Tidak Diketahui'] as $awal) {
            $k = $this->buatKucing(['status_steril' => $awal]);

            $this->postJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", $this->payload(['jenis_tindakan' => 'Sterilisasi']), $this->asRole('admin'))
                ->assertCreated();

            $this->assertSame('Sudah', $k->fresh()->status_steril);
            $this->assertDatabaseHas('audit_log', ['aktivitas' => 'SINKRON_STERIL', 'nama_tabel' => 'kucing', 'id_data' => $k->id_kucing]);
        }
    }

    public function test_sterilisasi_pada_kucing_yang_sudah_steril_tidak_menambah_audit_sinkron(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Sudah']);

        $this->postJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", $this->payload(['jenis_tindakan' => 'Sterilisasi']), $this->asRole('admin'))->assertCreated();

        $this->assertSame(0, AuditLog::where('aktivitas', 'SINKRON_STERIL')->count());
    }

    public function test_tindakan_lain_tidak_mengubah_status_steril(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Belum']);
        $h = $this->asRole('admin');

        foreach (['Vaksinasi', 'Pemeriksaan', 'Pemberian Vitamin', 'Obat Cacing', 'Obat Kutu', 'Penanganan Luka'] as $jenis) {
            $this->postJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", $this->payload(['jenis_tindakan' => $jenis]), $h)->assertCreated();
        }

        $this->assertSame('Belum', $k->fresh()->status_steril);
    }

    public function test_satu_kucing_hanya_boleh_satu_catatan_sterilisasi(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Belum']);
        $this->catat($k, 'Sterilisasi', '2026-01-01');
        $lain = $this->buatKucing(['status_steril' => 'Belum']);

        $this->postJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", $this->payload(['jenis_tindakan' => 'Sterilisasi']), $this->asRole('admin'))
            ->assertStatus(422)->assertJsonValidationErrors('jenis_tindakan');
        $this->assertSame(1, RiwayatKesehatan::where('id_kucing', $k->id_kucing)->where('jenis_tindakan', 'Sterilisasi')->count());

        // kucing lain tidak terpengaruh
        $this->postJson("/api/admin/kucing/{$lain->id_kucing}/kesehatan", $this->payload(['jenis_tindakan' => 'Sterilisasi']), $this->asRole('admin'))->assertCreated();
    }

    // ------------------------------------------------------------------ ubah

    public function test_ubah_sebagian_field_dan_audit(): void
    {
        $k = $this->buatKucing();
        $r = $this->catat($k, 'Vaksinasi', '2026-02-02', 'Lama');

        $this->putJson("/api/admin/kesehatan/{$r->id_kesehatan}", ['catatan' => '  Rabies  ', 'tanggal' => '2026-02-05'], $this->asRole('admin'))
            ->assertOk()
            ->assertJsonPath('data.catatan', 'Rabies')
            ->assertJsonPath('data.tanggal', '2026-02-05')
            ->assertJsonPath('data.jenis_tindakan', 'Vaksinasi');   // tidak berubah

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'riwayat_kesehatan', 'id_data' => $r->id_kesehatan]);
    }

    public function test_mengubah_jenis_menjadi_sterilisasi_menyinkronkan_status(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Belum']);
        $r = $this->catat($k, 'Pemeriksaan', '2026-02-02');

        $this->putJson("/api/admin/kesehatan/{$r->id_kesehatan}", ['jenis_tindakan' => 'Sterilisasi'], $this->asRole('admin'))->assertOk();

        $this->assertSame('Sudah', $k->fresh()->status_steril);
    }

    public function test_mengubah_jenis_menjadi_sterilisasi_ditolak_bila_sudah_ada_sterilisasi_lain(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Sudah']);
        $this->catat($k, 'Sterilisasi', '2026-01-01');
        $r = $this->catat($k, 'Pemeriksaan', '2026-02-02');

        $this->putJson("/api/admin/kesehatan/{$r->id_kesehatan}", ['jenis_tindakan' => 'Sterilisasi'], $this->asRole('admin'))
            ->assertStatus(422)->assertJsonValidationErrors('jenis_tindakan');
        $this->assertSame('Pemeriksaan', $r->fresh()->jenis_tindakan);
    }

    public function test_mengedit_catatan_sterilisasi_sendiri_tidak_dianggap_duplikat(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Sudah']);
        $r = $this->catat($k, 'Sterilisasi', '2026-01-01');

        $this->putJson("/api/admin/kesehatan/{$r->id_kesehatan}", ['jenis_tindakan' => 'Sterilisasi', 'catatan' => 'Klinik B'], $this->asRole('admin'))->assertOk();
    }

    public function test_mengubah_jenis_menjadi_lainnya_wajib_catatan_dan_ubah_tidak_ada_404(): void
    {
        $k = $this->buatKucing();
        $r = $this->catat($k, 'Vaksinasi', '2026-02-02');
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/kesehatan/{$r->id_kesehatan}", ['jenis_tindakan' => 'Lainnya'], $h)->assertStatus(422)->assertJsonValidationErrors('catatan');
        $this->putJson("/api/admin/kesehatan/{$r->id_kesehatan}", ['jenis_tindakan' => 'Lainnya', 'catatan' => 'Cek mata'], $h)->assertOk();
        $this->putJson('/api/admin/kesehatan/99999', ['catatan' => 'x'], $h)->assertStatus(404);
    }

    public function test_kucing_pemilik_catatan_tidak_dapat_dipindahkan_lewat_ubah(): void
    {
        $a = $this->buatKucing();
        $b = $this->buatKucing();
        $r = $this->catat($a, 'Vaksinasi', '2026-02-02');

        $this->putJson("/api/admin/kesehatan/{$r->id_kesehatan}", ['id_kucing' => $b->id_kucing, 'catatan' => 'x'], $this->asRole('admin'))->assertOk();

        $this->assertSame($a->id_kucing, $r->fresh()->id_kucing);
    }

    // ----------------------------------------------------------------- hapus

    public function test_hapus_catatan_dan_status_steril_tidak_dikembalikan(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Belum', 'status_kucing' => 'Terdata']);
        $h = $this->asRole('admin');
        $id = $this->postJson("/api/admin/kucing/{$k->id_kucing}/kesehatan", $this->payload(['jenis_tindakan' => 'Sterilisasi']), $h)
            ->assertCreated()->json('data.id_kesehatan');
        $this->assertSame('Sudah', $k->fresh()->status_steril);

        $this->deleteJson("/api/admin/kesehatan/{$id}", [], $h)->assertOk();

        $this->assertDatabaseMissing('riwayat_kesehatan', ['id_kesehatan' => $id]);
        $this->assertSame('Sudah', $k->fresh()->status_steril);   // tidak ditebak kembali ke Belum
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'DELETE', 'nama_tabel' => 'riwayat_kesehatan', 'id_data' => $id]);
    }

    public function test_hapus_catatan_tidak_ada_404(): void
    {
        $this->deleteJson('/api/admin/kesehatan/99999', [], $this->asRole('admin'))->assertStatus(404);
    }

    public function test_menghapus_kucing_tidak_boleh_bila_punya_riwayat_kesehatan(): void
    {
        $k = $this->buatKucing();
        $this->catat($k, 'Vaksinasi', '2026-02-02');

        $this->deleteJson("/api/admin/kucing/{$k->id_kucing}", [], $this->asRole('admin'))->assertStatus(409);
        $this->assertDatabaseCount('riwayat_kesehatan', 1);
    }
}

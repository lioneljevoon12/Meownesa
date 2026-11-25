<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Kucing;
use App\Models\PemberianMakan;
use App\Models\PengajuanAdopsi;
use App\Models\RiwayatKesehatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\TestCase;

/** dev-backend-04: pengelolaan data kucing oleh admin + aturan MEOW-BR-14. */
class KucingAdminApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
    }

    private function payload(array $o = []): array
    {
        return $o + ['id_lokasi' => $this->danau->id_lokasi, 'nama_kucing' => 'Mochi', 'jenis_kelamin' => 'Betina'];
    }

    // ------------------------------------------------------------ hak akses

    public function test_semua_endpoint_admin_kucing_butuh_role_admin(): void
    {
        $k = $this->buatKucing();
        $rute = [
            ['getJson', '/api/admin/kucing'],
            ['getJson', "/api/admin/kucing/{$k->id_kucing}"],
            ['postJson', '/api/admin/kucing'],
            ['putJson', "/api/admin/kucing/{$k->id_kucing}"],
            ['deleteJson', "/api/admin/kucing/{$k->id_kucing}"],
        ];

        // getJson menerima ($uri, $headers); metode lain ($uri, $data, $headers)
        $panggil = fn (string $metode, string $uri, array $h = []) => $metode === 'getJson'
            ? $this->getJson($uri, $h)
            : $this->$metode($uri, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();   // guard menyimpan user dari request sebelumnya dalam satu tes
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);
        }

        $this->assertDatabaseCount('kucing', 1);
    }

    // ------------------------------------------------------------------ baca

    public function test_admin_melihat_semua_status_dengan_data_tambahan(): void
    {
        foreach (['Terdata', 'Tersedia', 'Proses Screening', 'Diadopsi', 'Hilang', 'Almarhum'] as $status) {
            $this->buatKucing(['status_kucing' => $status]);
        }
        $k = Kucing::first();
        $this->buatFoto($k, true);
        $h = $this->asRole('admin');

        $res = $this->getJson('/api/admin/kucing', $h)->assertOk()->assertJsonPath('meta.total', 6);
        $this->assertArrayHasKey('created_at', $res->json('data.0'));
        $this->assertArrayHasKey('jumlah_foto', $res->json('data.0'));

        $this->getJson('/api/admin/kucing?status=Almarhum', $h)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/kucing?status=Bukan', $h)->assertStatus(422);
        $this->getJson("/api/admin/kucing/{$k->id_kucing}", $h)->assertOk()->assertJsonCount(1, 'data.galeri');
        $this->getJson('/api/admin/kucing/99999', $h)->assertStatus(404);
    }

    // ---------------------------------------------------------------- tambah

    public function test_tambah_kucing_dengan_kode_otomatis_per_kampus_dan_audit(): void
    {
        $h = $this->asRole('admin');

        $a = $this->postJson('/api/admin/kucing', $this->payload(), $h)->assertCreated()
            ->assertJsonPath('data.kode_kucing', 'KTT-001')
            ->assertJsonPath('data.status_kucing', 'Terdata')            // default
            ->assertJsonPath('data.status_steril', 'Tidak Diketahui')    // default: tidak ditebak
            ->assertJsonPath('data.lokasi.nama_kampus', 'Kampus Ketintang');
        $this->postJson('/api/admin/kucing', $this->payload(['nama_kucing' => 'Milo']), $h)->assertCreated()->assertJsonPath('data.kode_kucing', 'KTT-002');
        $this->postJson('/api/admin/kucing', $this->payload(['id_lokasi' => $this->fip->id_lokasi]), $h)->assertCreated()->assertJsonPath('data.kode_kucing', 'LWT-001');

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'kucing', 'id_data' => $a->json('data.id_kucing')]);
    }

    public function test_kode_manual_dirapikan_dan_harus_unik(): void
    {
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/kucing', $this->payload(['kode_kucing' => ' kk-77 ']), $h)->assertCreated()->assertJsonPath('data.kode_kucing', 'KK-77');
        $this->postJson('/api/admin/kucing', $this->payload(['kode_kucing' => 'KK-77']), $h)->assertStatus(422)
            ->assertJsonPath('errors.kode_kucing.0', 'Kode kucing sudah dipakai kucing lain.');
        $this->postJson('/api/admin/kucing', $this->payload(['kode_kucing' => 'a b!']), $h)->assertStatus(422)->assertJsonValidationErrors('kode_kucing');
    }

    public function test_validasi_tambah_kucing(): void
    {
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/kucing', [], $h)->assertStatus(422)
            ->assertJsonValidationErrors(['id_lokasi', 'nama_kucing', 'jenis_kelamin']);

        foreach ([
            ['id_lokasi' => 999],
            ['jenis_kelamin' => 'Robot'],
            ['kelompok_usia' => 'Kakek'],
            ['status_steril' => 'Mungkin'],
            ['nama_kucing' => str_repeat('a', 101)],
            ['deskripsi' => str_repeat('a', 2001)],
        ] as $salah) {
            $this->postJson('/api/admin/kucing', $this->payload($salah), $h)->assertStatus(422);
        }

        $this->assertDatabaseCount('kucing', 0);
        $this->assertSame(0, AuditLog::where('nama_tabel', 'kucing')->count());
    }

    public function test_status_alur_adopsi_tidak_bisa_diisi_manual(): void
    {
        $h = $this->asRole('admin');

        foreach (['Diadopsi', 'Proses Screening'] as $status) {
            $this->postJson('/api/admin/kucing', $this->payload(['status_kucing' => $status]), $h)->assertStatus(422)
                ->assertJsonValidationErrors('status_kucing');
        }
        $this->postJson('/api/admin/kucing', $this->payload(['status_kucing' => 'Hilang']), $h)->assertCreated();
        $this->postJson('/api/admin/kucing', $this->payload(['status_kucing' => 'Almarhum']), $h)->assertCreated();
    }

    // ------------------------------------------------------- MEOW-BR-14

    public function test_tersedia_wajib_steril_dan_konfirmasi_sehat_saat_tambah(): void
    {
        $h = $this->asRole('admin');
        $tersedia = $this->payload(['status_kucing' => 'Tersedia']);

        // belum steril / tidak diketahui
        $this->postJson('/api/admin/kucing', $tersedia + ['status_steril' => 'Belum', 'konfirmasi_sehat' => true], $h)
            ->assertStatus(422)->assertJsonValidationErrors('status_kucing');
        $this->postJson('/api/admin/kucing', $tersedia + ['konfirmasi_sehat' => true], $h)->assertStatus(422);   // steril default: Tidak Diketahui

        // steril tapi belum dikonfirmasi sehat
        $this->postJson('/api/admin/kucing', $tersedia + ['status_steril' => 'Sudah'], $h)
            ->assertStatus(422)->assertJsonValidationErrors('konfirmasi_sehat');
        $this->postJson('/api/admin/kucing', $tersedia + ['status_steril' => 'Sudah', 'konfirmasi_sehat' => false], $h)->assertStatus(422);

        $this->postJson('/api/admin/kucing', $tersedia + ['status_steril' => 'Sudah', 'konfirmasi_sehat' => true], $h)
            ->assertCreated()->assertJsonPath('data.status_kucing', 'Tersedia');
    }

    public function test_menaikkan_status_ke_tersedia_saat_ubah(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Sudah', 'status_kucing' => 'Terdata']);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/kucing/{$k->id_kucing}", ['status_kucing' => 'Tersedia'], $h)
            ->assertStatus(422)->assertJsonValidationErrors('konfirmasi_sehat');
        $this->assertSame('Terdata', $k->fresh()->status_kucing);

        $this->putJson("/api/admin/kucing/{$k->id_kucing}", ['status_kucing' => 'Tersedia', 'konfirmasi_sehat' => true], $h)
            ->assertOk()->assertJsonPath('data.status_kucing', 'Tersedia');
    }

    public function test_belum_steril_tidak_bisa_dinaikkan_ke_tersedia_saat_ubah(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Belum', 'status_kucing' => 'Terdata']);

        $this->putJson("/api/admin/kucing/{$k->id_kucing}", ['status_kucing' => 'Tersedia', 'konfirmasi_sehat' => true], $this->asRole('admin'))
            ->assertStatus(422)->assertJsonValidationErrors('status_kucing');
    }

    public function test_kucing_tersedia_tidak_boleh_menjadi_belum_steril_tanpa_menurunkan_status(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Sudah', 'status_kucing' => 'Tersedia']);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/kucing/{$k->id_kucing}", ['status_steril' => 'Belum'], $h)
            ->assertStatus(422)->assertJsonValidationErrors('status_steril');
        $this->assertSame('Sudah', $k->fresh()->status_steril);

        // diturunkan dulu, baru boleh
        $this->putJson("/api/admin/kucing/{$k->id_kucing}", ['status_kucing' => 'Terdata', 'status_steril' => 'Belum'], $h)->assertOk();
    }

    public function test_mengedit_kucing_yang_sudah_tersedia_tidak_perlu_konfirmasi_ulang(): void
    {
        $k = $this->buatKucing(['status_steril' => 'Sudah', 'status_kucing' => 'Tersedia']);

        $this->putJson("/api/admin/kucing/{$k->id_kucing}", ['deskripsi' => 'Update ciri'], $this->asRole('admin'))
            ->assertOk()->assertJsonPath('data.deskripsi', 'Update ciri');
    }

    // ------------------------------------------------------------------ ubah

    public function test_ubah_sebagian_field_dan_audit(): void
    {
        $k = $this->buatKucing(['nama_kucing' => 'Lama']);

        $this->putJson("/api/admin/kucing/{$k->id_kucing}", ['nama_kucing' => '  Baru  ', 'id_lokasi' => $this->fip->id_lokasi], $this->asRole('admin'))
            ->assertOk()
            ->assertJsonPath('data.nama_kucing', 'Baru')
            ->assertJsonPath('data.lokasi.nama_lokasi', 'FIP')
            ->assertJsonPath('data.jenis_kelamin', 'Betina');           // tidak berubah

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'kucing', 'id_data' => $k->id_kucing]);
    }

    public function test_kode_duplikat_ditolak_tetapi_kode_sendiri_boleh(): void
    {
        $a = $this->buatKucing(['kode_kucing' => 'AAA-1']);
        $b = $this->buatKucing(['kode_kucing' => 'BBB-1']);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/kucing/{$b->id_kucing}", ['kode_kucing' => 'AAA-1'], $h)->assertStatus(422);
        $this->putJson("/api/admin/kucing/{$b->id_kucing}", ['kode_kucing' => 'bbb-1', 'nama_kucing' => 'X'], $h)->assertOk();
    }

    public function test_status_diadopsi_tidak_bisa_dipilih_manual_tetapi_kucing_diadopsi_tetap_bisa_diedit(): void
    {
        $biasa = $this->buatKucing(['status_kucing' => 'Terdata']);
        $adopsi = $this->buatKucing(['status_kucing' => 'Diadopsi']);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/kucing/{$biasa->id_kucing}", ['status_kucing' => 'Diadopsi'], $h)->assertStatus(422);
        $this->putJson("/api/admin/kucing/{$biasa->id_kucing}", ['status_kucing' => 'Proses Screening'], $h)->assertStatus(422);

        // kucing yang memang sudah Diadopsi: kirim ulang status yang sama + edit lain = OK
        $this->putJson("/api/admin/kucing/{$adopsi->id_kucing}", ['status_kucing' => 'Diadopsi', 'deskripsi' => 'Catatan'], $h)->assertOk();
        // dan boleh dikeluarkan dari status itu
        $this->putJson("/api/admin/kucing/{$adopsi->id_kucing}", ['status_kucing' => 'Terdata'], $h)->assertOk();
    }

    public function test_ubah_kucing_tidak_ada_404(): void
    {
        $this->putJson('/api/admin/kucing/99999', ['nama_kucing' => 'X'], $this->asRole('admin'))->assertStatus(404);
    }

    // ----------------------------------------------------------------- hapus

    public function test_hapus_kucing_tanpa_riwayat_beserta_foto_dan_berkas_unggahan(): void
    {
        Storage::fake('kucing_foto');
        $k = $this->buatKucing();
        $foto = $this->buatFoto($k, true, 'images/kucing/upload/hapus-aku.jpg');
        Storage::disk('kucing_foto')->put('upload/hapus-aku.jpg', 'x');

        $this->deleteJson("/api/admin/kucing/{$k->id_kucing}", [], $this->asRole('admin'))->assertOk();

        $this->assertDatabaseMissing('kucing', ['id_kucing' => $k->id_kucing]);
        $this->assertDatabaseMissing('foto_kucing', ['id_foto_kucing' => $foto->id_foto_kucing]);
        Storage::disk('kucing_foto')->assertMissing('upload/hapus-aku.jpg');
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'DELETE', 'nama_tabel' => 'kucing', 'id_data' => $k->id_kucing]);
    }

    public function test_hapus_kucing_yang_sudah_punya_riwayat_ditolak_409_dan_data_aman(): void
    {
        $h = $this->asRole('admin');

        $a = $this->buatKucing();
        RiwayatKesehatan::create(['id_kucing' => $a->id_kucing, 'jenis_tindakan' => 'Sterilisasi', 'tanggal' => '2026-01-01']);
        $b = $this->buatKucing();
        PengajuanAdopsi::create([
            'id_kucing' => $b->id_kucing, 'nomor_pengajuan' => 'ADP-2026-900', 'kode_tracking' => 'TRK900', 'nama_pemohon' => 'X',
            'nomor_whatsapp' => '081', 'email' => 'x@y.z', 'alamat' => 'Jl. A', 'status_pengajuan' => 'MENUNGGU', 'tanggal_pengajuan' => '2026-11-01',
        ]);
        $c = $this->buatKucing();
        PemberianMakan::create(['id_kucing' => $c->id_kucing, 'id_lokasi' => $this->danau->id_lokasi, 'tanggal' => '2026-11-01', 'status_pemberian' => 'Tuntas']);

        foreach ([$a, $b, $c] as $kucing) {
            $this->deleteJson("/api/admin/kucing/{$kucing->id_kucing}", [], $h)->assertStatus(409);
            $this->assertDatabaseHas('kucing', ['id_kucing' => $kucing->id_kucing]);
        }
        $this->assertSame(0, AuditLog::where('aktivitas', 'DELETE')->count());
        $this->assertDatabaseCount('riwayat_kesehatan', 1);
        $this->assertDatabaseCount('pengajuan_adopsi', 1);
    }

    public function test_hapus_kucing_tidak_ada_404(): void
    {
        $this->deleteJson('/api/admin/kucing/99999', [], $this->asRole('admin'))->assertStatus(404);
    }
}

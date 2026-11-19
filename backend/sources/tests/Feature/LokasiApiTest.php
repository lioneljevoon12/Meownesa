<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Kampus;
use App\Models\Kucing;
use App\Models\Lokasi;
use App\Models\PemberianMakan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

/** dev-backend-03: kampus & master lokasi (publik + admin). */
class LokasiApiTest extends TestCase
{
    use InteractsWithApiAuth, RefreshDatabase;

    private Kampus $ktt;
    private Kampus $lwt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ktt = Kampus::create(['nama_kampus' => 'Kampus Ketintang']);
        $this->lwt = Kampus::create(['nama_kampus' => 'Kampus Lidah Wetan']);
    }

    private function lokasi(Kampus $k, string $nama): Lokasi
    {
        return Lokasi::create(['id_kampus' => $k->id_kampus, 'nama_lokasi' => $nama, 'koordinat_x' => -7.3129, 'koordinat_y' => 112.7275]);
    }

    private function kucing(Lokasi $l, string $kode, string $status = 'Terdata'): Kucing
    {
        return Kucing::create([
            'id_lokasi' => $l->id_lokasi, 'kode_kucing' => $kode, 'nama_kucing' => 'Uji '.$kode,
            'jenis_kelamin' => 'Betina', 'status_steril' => 'Sudah', 'status_kucing' => $status,
        ]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'id_kampus' => $this->ktt->id_kampus, 'nama_lokasi' => 'Gedung Baru',
            'koordinat_x' => -7.31234567, 'koordinat_y' => 112.72765432,
        ], $override);
    }

    // ---------------------------------------------------------------- publik

    public function test_daftar_kampus_publik_dengan_jumlah_lokasi_dan_kucing_di_kampus(): void
    {
        $danau = $this->lokasi($this->ktt, 'Danau');
        $this->lokasi($this->ktt, 'FEB');
        $this->kucing($danau, 'KTT-001');
        $this->kucing($danau, 'KTT-002', 'Diadopsi');   // sudah tidak di kampus -> tidak dihitung
        $this->kucing($danau, 'KTT-003', 'Hilang');

        $this->getJson('/api/kampus')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nama_kampus', 'Kampus Ketintang')
            ->assertJsonPath('data.0.jumlah_lokasi', 2)
            ->assertJsonPath('data.0.jumlah_kucing', 1)
            ->assertJsonPath('data.1.jumlah_lokasi', 0);
    }

    public function test_daftar_lokasi_publik_dan_filter_kampus(): void
    {
        $this->lokasi($this->ktt, 'Danau');
        $this->lokasi($this->ktt, 'FEB');
        $this->lokasi($this->lwt, 'FIP');

        $this->getJson('/api/lokasi')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id_lokasi', 'id_kampus', 'nama_lokasi', 'koordinat_x', 'koordinat_y', 'jumlah_kucing', 'kampus' => ['id_kampus', 'nama_kampus']]]]);

        $this->getJson('/api/lokasi?id_kampus='.$this->lwt->id_kampus)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama_lokasi', 'FIP');
    }

    public function test_filter_kampus_tidak_valid_422(): void
    {
        $this->getJson('/api/lokasi?id_kampus=999')->assertStatus(422)->assertJsonValidationErrors('id_kampus');
        $this->getJson('/api/lokasi?id_kampus=abc')->assertStatus(422);
    }

    public function test_detail_lokasi_dan_404(): void
    {
        $l = $this->lokasi($this->ktt, 'Danau');
        $this->kucing($l, 'KTT-001');

        $this->getJson("/api/lokasi/{$l->id_lokasi}")->assertOk()
            ->assertJsonPath('data.nama_lokasi', 'Danau')
            ->assertJsonPath('data.jumlah_kucing', 1)
            ->assertJsonPath('data.kampus.nama_kampus', 'Kampus Ketintang');

        $this->getJson('/api/lokasi/9999')->assertStatus(404)->assertJsonPath('success', false);
        $this->getJson('/api/lokasi/abc')->assertStatus(404);
    }

    public function test_payload_publik_tidak_memuat_kolom_internal(): void
    {
        $this->lokasi($this->ktt, 'Danau');

        $item = $this->getJson('/api/lokasi')->assertOk()->json('data.0');
        $this->assertEqualsCanonicalizing(
            ['id_lokasi', 'id_kampus', 'nama_lokasi', 'koordinat_x', 'koordinat_y', 'jumlah_kucing', 'kampus'],
            array_keys($item)
        );
    }

    // ------------------------------------------------------------ hak akses

    public function test_tulis_lokasi_hanya_untuk_admin(): void
    {
        $l = $this->lokasi($this->ktt, 'Danau');

        $this->postJson('/api/admin/lokasi', $this->payload())->assertStatus(401);
        $this->postJson('/api/admin/lokasi', $this->payload(), $this->asRole('superadmin'))->assertStatus(403);
        $this->postJson('/api/admin/lokasi', $this->payload(), $this->asRole('adopter'))->assertStatus(403);
        $this->putJson("/api/admin/lokasi/{$l->id_lokasi}", ['nama_lokasi' => 'X'], $this->asRole('superadmin'))->assertStatus(403);
        $this->deleteJson("/api/admin/lokasi/{$l->id_lokasi}", [], $this->asRole('adopter'))->assertStatus(403);

        $this->assertDatabaseCount('lokasi', 1);
    }

    // ---------------------------------------------------------------- tambah

    public function test_admin_menambah_lokasi_dan_tercatat_di_audit(): void
    {
        $h = $this->asRole('admin');

        $res = $this->postJson('/api/admin/lokasi', $this->payload(['nama_lokasi' => '  Gedung   Baru ']), $h)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama_lokasi', 'Gedung Baru')       // spasi dirapikan
            ->assertJsonPath('data.kampus.nama_kampus', 'Kampus Ketintang')
            ->assertJsonPath('data.jumlah_kucing', 0);

        $id = $res->json('data.id_lokasi');
        $this->assertDatabaseHas('lokasi', ['id_lokasi' => $id, 'nama_lokasi' => 'Gedung Baru']);
        $this->assertEqualsWithDelta(-7.31234567, Lokasi::find($id)->koordinat_x, 0.000000001);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'lokasi', 'id_data' => $id]);
    }

    public function test_validasi_tambah_lokasi(): void
    {
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/lokasi', [], $h)->assertStatus(422)
            ->assertJsonValidationErrors(['id_kampus', 'nama_lokasi', 'koordinat_x', 'koordinat_y']);

        $this->postJson('/api/admin/lokasi', $this->payload(['id_kampus' => 999]), $h)->assertStatus(422)
            ->assertJsonPath('errors.id_kampus.0', 'Kampus tidak ditemukan. Lokasi wajib terikat pada kampus resmi UNESA.');
        $this->postJson('/api/admin/lokasi', $this->payload(['koordinat_x' => 112.7]), $h)->assertStatus(422)->assertJsonValidationErrors('koordinat_x');
        $this->postJson('/api/admin/lokasi', $this->payload(['koordinat_y' => 181]), $h)->assertStatus(422)->assertJsonValidationErrors('koordinat_y');
        $this->postJson('/api/admin/lokasi', $this->payload(['koordinat_x' => 'utara']), $h)->assertStatus(422);
        $this->postJson('/api/admin/lokasi', $this->payload(['koordinat_x' => -7.123456789]), $h)->assertStatus(422);
        $this->postJson('/api/admin/lokasi', $this->payload(['nama_lokasi' => str_repeat('a', 101)]), $h)->assertStatus(422);

        $this->assertDatabaseCount('lokasi', 0);
        $this->assertSame(0, AuditLog::where('nama_tabel', 'lokasi')->count());
    }

    public function test_nama_lokasi_unik_per_kampus(): void
    {
        $this->lokasi($this->ktt, 'Danau');
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/lokasi', $this->payload(['nama_lokasi' => 'Danau']), $h)->assertStatus(422)
            ->assertJsonPath('errors.nama_lokasi.0', 'Nama lokasi sudah dipakai di kampus ini.');

        // nama sama di kampus lain boleh
        $this->postJson('/api/admin/lokasi', $this->payload(['nama_lokasi' => 'Danau', 'id_kampus' => $this->lwt->id_kampus]), $h)
            ->assertCreated();
    }

    // ------------------------------------------------------------------ ubah

    public function test_admin_mengubah_sebagian_field_dan_tercatat(): void
    {
        $l = $this->lokasi($this->ktt, 'Danau');
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/lokasi/{$l->id_lokasi}", ['koordinat_x' => -7.3101, 'koordinat_y' => 112.7302], $h)
            ->assertOk()
            ->assertJsonPath('data.nama_lokasi', 'Danau');               // field lain tetap

        $l->refresh();
        $this->assertEqualsWithDelta(-7.3101, $l->koordinat_x, 0.00001);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'lokasi', 'id_data' => $l->id_lokasi]);
    }

    public function test_ubah_nama_menjadi_duplikat_ditolak_tapi_nama_sendiri_boleh(): void
    {
        $this->lokasi($this->ktt, 'FEB');
        $l = $this->lokasi($this->ktt, 'Danau');
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/lokasi/{$l->id_lokasi}", ['nama_lokasi' => 'FEB'], $h)->assertStatus(422);
        $this->putJson("/api/admin/lokasi/{$l->id_lokasi}", ['nama_lokasi' => 'Danau'], $h)->assertOk();
    }

    public function test_pindah_kampus_mengecek_duplikat_di_kampus_tujuan(): void
    {
        $this->lokasi($this->lwt, 'Danau');
        $l = $this->lokasi($this->ktt, 'Danau');
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/lokasi/{$l->id_lokasi}", ['id_kampus' => $this->lwt->id_kampus], $h)->assertStatus(422);
    }

    public function test_ubah_lokasi_tidak_ada_404(): void
    {
        $this->putJson('/api/admin/lokasi/9999', ['nama_lokasi' => 'X'], $this->asRole('admin'))->assertStatus(404);
    }

    // ----------------------------------------------------------------- hapus

    public function test_hapus_lokasi_kosong_dan_tercatat(): void
    {
        $l = $this->lokasi($this->ktt, 'PKK');

        $this->deleteJson("/api/admin/lokasi/{$l->id_lokasi}", [], $this->asRole('admin'))->assertOk();

        $this->assertDatabaseMissing('lokasi', ['id_lokasi' => $l->id_lokasi]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'DELETE', 'nama_tabel' => 'lokasi', 'id_data' => $l->id_lokasi]);
    }

    public function test_hapus_lokasi_yang_masih_dipakai_kucing_ditolak_409_dan_data_aman(): void
    {
        $l = $this->lokasi($this->ktt, 'Danau');
        $k = $this->kucing($l, 'KTT-001', 'Diadopsi');   // status apa pun tetap dihitung

        $this->deleteJson("/api/admin/lokasi/{$l->id_lokasi}", [], $this->asRole('admin'))
            ->assertStatus(409)
            ->assertJsonPath('data.jumlah_kucing', 1);

        $this->assertDatabaseHas('lokasi', ['id_lokasi' => $l->id_lokasi]);
        $this->assertDatabaseHas('kucing', ['id_kucing' => $k->id_kucing]);
        $this->assertSame(0, AuditLog::where('aktivitas', 'DELETE')->count());
    }

    public function test_hapus_lokasi_yang_punya_feeding_log_ditolak_409(): void
    {
        $l = $this->lokasi($this->ktt, 'Danau');
        PemberianMakan::create(['id_lokasi' => $l->id_lokasi, 'tanggal' => '2026-11-01', 'status_pemberian' => 'Tuntas']);

        $this->deleteJson("/api/admin/lokasi/{$l->id_lokasi}", [], $this->asRole('admin'))
            ->assertStatus(409)
            ->assertJsonPath('data.jumlah_pemberian_makan', 1);
    }
}

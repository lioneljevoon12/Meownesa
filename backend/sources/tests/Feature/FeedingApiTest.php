<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PemberianMakan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\TestCase;

/** dev-backend-06: feeding log (pencatatan, sinkronisasi massal/idempoten, rekap). */
class FeedingApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, RefreshDatabase;

    private string $hariIni;
    private string $besok;
    private string $kemarin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
        $wib = Carbon::now('Asia/Jakarta');
        $this->hariIni = $wib->toDateString();
        $this->besok = $wib->copy()->addDay()->toDateString();
        $this->kemarin = $wib->copy()->subDay()->toDateString();
    }

    private function catat(string $tanggal, $lokasi, $kucing = null, string $status = 'Tuntas', ?string $catatan = null, ?string $uuid = null): PemberianMakan
    {
        return PemberianMakan::create([
            'client_uuid' => $uuid, 'id_lokasi' => $lokasi->id_lokasi, 'id_kucing' => $kucing?->id_kucing,
            'tanggal' => $tanggal, 'status_pemberian' => $status, 'catatan' => $catatan,
        ]);
    }

    private function payload(array $o = []): array
    {
        return $o + ['id_lokasi' => $this->danau->id_lokasi, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas'];
    }

    // ------------------------------------------------------------ hak akses

    public function test_semua_endpoint_feeding_butuh_role_admin(): void
    {
        $r = $this->catat($this->hariIni, $this->danau);
        $rute = [
            ['getJson', '/api/admin/feeding'],
            ['getJson', '/api/admin/feeding/rekap'],
            ['postJson', '/api/admin/feeding'],
            ['postJson', '/api/admin/feeding/batch'],
            ['putJson', "/api/admin/feeding/{$r->id_pemberian}"],
            ['deleteJson', "/api/admin/feeding/{$r->id_pemberian}"],
        ];
        $panggil = fn (string $m, string $u, array $h = []) => $m === 'getJson' ? $this->getJson($u, $h) : $this->$m($u, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);
        }
        $this->assertDatabaseCount('pemberian_makan', 1);
    }

    // ---------------------------------------------------------------- tambah

    public function test_catat_feeding_untuk_titik_lokasi_tanpa_kucing(): void
    {
        $res = $this->postJson('/api/admin/feeding', $this->payload(['catatan' => 'Pakan kering 65gr']), $this->asRole('admin'))
            ->assertCreated()
            ->assertJsonPath('data.kucing', null)
            ->assertJsonPath('data.lokasi.nama_lokasi', 'Danau')
            ->assertJsonPath('data.catatan', 'Pakan kering 65gr')
            ->assertJsonPath('data.tanggal', $this->hariIni);

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'pemberian_makan', 'id_data' => $res->json('data.id_pemberian')]);
    }

    public function test_catat_feeding_untuk_kucing_mengisi_lokasi_dari_kucing(): void
    {
        $k = $this->buatKucing(['id_lokasi' => $this->fip->id_lokasi]);

        $this->postJson('/api/admin/feeding', ['id_kucing' => $k->id_kucing, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Belum'], $this->asRole('admin'))
            ->assertCreated()
            ->assertJsonPath('data.lokasi.nama_lokasi', 'FIP')
            ->assertJsonPath('data.kucing.kode_kucing', $k->kode_kucing)
            ->assertJsonPath('data.status_pemberian', 'Belum');
    }

    public function test_kucing_boleh_diberi_makan_di_lokasi_lain_dari_lokasi_tetapnya(): void
    {
        $k = $this->buatKucing(['id_lokasi' => $this->danau->id_lokasi]);

        $this->postJson('/api/admin/feeding', $this->payload(['id_lokasi' => $this->fip->id_lokasi, 'id_kucing' => $k->id_kucing]), $this->asRole('admin'))
            ->assertCreated()->assertJsonPath('data.lokasi.nama_lokasi', 'FIP');
    }

    public function test_validasi_tambah_feeding(): void
    {
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/feeding', [], $h)->assertStatus(422)->assertJsonValidationErrors(['id_lokasi', 'tanggal', 'status_pemberian']);

        foreach ([
            ['id_lokasi' => 999],
            ['id_kucing' => 999],
            ['tanggal' => '19-11-2026'],
            ['tanggal' => '2019-12-31'],
            ['status_pemberian' => 'Mungkin'],
            ['catatan' => str_repeat('a', 1001)],
            ['client_uuid' => 'bukan-uuid'],
        ] as $salah) {
            $this->postJson('/api/admin/feeding', $this->payload($salah), $h)->assertStatus(422);
        }
        $this->assertDatabaseCount('pemberian_makan', 0);
        $this->assertSame(0, AuditLog::where('nama_tabel', 'pemberian_makan')->count());
    }

    public function test_aturan_tanggal_dan_status(): void
    {
        $h = $this->asRole('admin');

        // Tuntas/Belum tidak boleh di masa depan; rencana pakai Terjadwal
        $this->postJson('/api/admin/feeding', $this->payload(['tanggal' => $this->besok, 'status_pemberian' => 'Tuntas']), $h)->assertStatus(422)->assertJsonValidationErrors('tanggal');
        $this->postJson('/api/admin/feeding', $this->payload(['tanggal' => $this->besok, 'status_pemberian' => 'Belum']), $h)->assertStatus(422);
        $this->postJson('/api/admin/feeding', $this->payload(['tanggal' => $this->besok, 'status_pemberian' => 'Terjadwal']), $h)->assertCreated();

        // Terjadwal harus hari ini atau setelahnya
        $this->postJson('/api/admin/feeding', $this->payload(['tanggal' => $this->kemarin, 'status_pemberian' => 'Terjadwal']), $h)->assertStatus(422)->assertJsonValidationErrors('tanggal');
        $this->postJson('/api/admin/feeding', $this->payload(['tanggal' => $this->hariIni, 'status_pemberian' => 'Terjadwal']), $h)->assertCreated();
    }

    public function test_kucing_yang_tidak_lagi_di_kampus_tidak_bisa_dicatat(): void
    {
        $h = $this->asRole('admin');

        foreach (['Almarhum', 'Hilang', 'Diadopsi'] as $status) {
            $k = $this->buatKucing(['status_kucing' => $status]);
            $this->postJson('/api/admin/feeding', ['id_kucing' => $k->id_kucing, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas'], $h)
                ->assertStatus(422)->assertJsonValidationErrors('id_kucing');
        }
        foreach (['Terdata', 'Tersedia', 'Proses Screening'] as $status) {
            $k = $this->buatKucing(['status_kucing' => $status]);
            $this->postJson('/api/admin/feeding', ['id_kucing' => $k->id_kucing, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas'], $h)->assertCreated();
        }
    }

    public function test_satu_kunci_tanggal_lokasi_kucing_hanya_satu_catatan(): void
    {
        $k = $this->buatKucing();
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/feeding', $this->payload(['id_kucing' => $k->id_kucing]), $h)->assertCreated();
        $dup = $this->postJson('/api/admin/feeding', $this->payload(['id_kucing' => $k->id_kucing, 'status_pemberian' => 'Belum']), $h)
            ->assertStatus(422)->assertJsonValidationErrors('tanggal');
        $this->assertStringContainsString('id_pemberian', $dup->json('errors.tanggal.0'));

        // tanggal lain, kucing lain, dan catatan level-lokasi untuk hari yang sama: semuanya boleh
        $this->postJson('/api/admin/feeding', $this->payload(['id_kucing' => $k->id_kucing, 'tanggal' => $this->kemarin]), $h)->assertCreated();
        $this->postJson('/api/admin/feeding', $this->payload(['id_kucing' => $this->buatKucing()->id_kucing]), $h)->assertCreated();
        $this->postJson('/api/admin/feeding', $this->payload(), $h)->assertCreated();
        // ... tetapi catatan level-lokasi kedua pada hari yang sama ditolak
        $this->postJson('/api/admin/feeding', $this->payload(), $h)->assertStatus(422);
    }

    // ----------------------------------------------------------- idempotensi

    public function test_kirim_ulang_dengan_client_uuid_yang_sama_tidak_menggandakan_data(): void
    {
        $uuid = (string) Str::uuid();
        $h = $this->asRole('admin');

        $a = $this->postJson('/api/admin/feeding', $this->payload(['client_uuid' => $uuid, 'catatan' => 'pertama']), $h)->assertCreated();
        $b = $this->postJson('/api/admin/feeding', $this->payload(['client_uuid' => $uuid, 'catatan' => 'isi berbeda']), $h)
            ->assertOk()                                           // 200, bukan 422 duplikat
            ->assertJsonPath('data.catatan', 'pertama');           // catatan asli tidak tertimpa

        $this->assertSame($a->json('data.id_pemberian'), $b->json('data.id_pemberian'));
        $this->assertDatabaseCount('pemberian_makan', 1);
        $this->assertSame(1, AuditLog::where('aktivitas', 'CREATE')->where('nama_tabel', 'pemberian_makan')->count());
    }

    public function test_client_uuid_berbeda_untuk_kunci_yang_sama_tetap_dianggap_duplikat(): void
    {
        $h = $this->asRole('admin');
        $this->postJson('/api/admin/feeding', $this->payload(['client_uuid' => (string) Str::uuid()]), $h)->assertCreated();

        $this->postJson('/api/admin/feeding', $this->payload(['client_uuid' => (string) Str::uuid()]), $h)->assertStatus(422);
    }

    // ----------------------------------------------------------------- batch

    public function test_batch_memproses_tiap_catatan_secara_mandiri(): void
    {
        $k = $this->buatKucing(['id_lokasi' => $this->fip->id_lokasi]);
        $sudah = (string) Str::uuid();
        $this->catat($this->kemarin, $this->danau, null, 'Tuntas', null, $sudah);     // sudah tersimpan sebelumnya
        $this->catat($this->hariIni, $this->fip);                                      // kunci yang akan bentrok

        $res = $this->postJson('/api/admin/feeding/batch', ['entries' => [
            ['client_uuid' => (string) Str::uuid(), 'id_lokasi' => $this->danau->id_lokasi, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas'],   // 0 dibuat
            ['client_uuid' => (string) Str::uuid(), 'id_kucing' => $k->id_kucing, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Belum'],             // 1 dibuat (lokasi dari kucing)
            ['client_uuid' => $sudah, 'id_lokasi' => $this->danau->id_lokasi, 'tanggal' => $this->kemarin, 'status_pemberian' => 'Tuntas'],                // 2 sudah_ada
            ['client_uuid' => (string) Str::uuid(), 'id_lokasi' => $this->danau->id_lokasi, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Mungkin'],  // 3 ditolak (status)
            ['client_uuid' => (string) Str::uuid(), 'id_lokasi' => $this->fip->id_lokasi, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas'],     // 4 ditolak (duplikat di DB)
            ['client_uuid' => (string) Str::uuid(), 'id_lokasi' => $this->danau->id_lokasi, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Belum'],    // 5 ditolak (duplikat dari entri 0 di batch yang sama)
        ]], $this->asRole('admin'))->assertOk()->assertJsonPath('success', true);

        $this->assertSame(['dibuat' => 2, 'sudah_ada' => 1, 'ditolak' => 3], $res->json('data.ringkasan'));
        $status = collect($res->json('data.hasil'))->pluck('status')->all();
        $this->assertSame(['dibuat', 'dibuat', 'sudah_ada', 'ditolak', 'ditolak', 'ditolak'], $status);
        $this->assertArrayHasKey('status_pemberian', $res->json('data.hasil.3.errors'));
        $this->assertArrayHasKey('tanggal', $res->json('data.hasil.4.errors'));
        $this->assertArrayHasKey('tanggal', $res->json('data.hasil.5.errors'));
        $this->assertSame($sudah, $res->json('data.hasil.2.client_uuid'));
        $this->assertNotNull($res->json('data.hasil.0.id_pemberian'));

        $this->assertDatabaseCount('pemberian_makan', 4);   // 2 awal + 2 baru
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'BATCH_CREATE', 'nama_tabel' => 'pemberian_makan']);
    }

    public function test_batch_diulang_seluruhnya_aman(): void
    {
        $entries = [
            ['client_uuid' => (string) Str::uuid(), 'id_lokasi' => $this->danau->id_lokasi, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas'],
            ['client_uuid' => (string) Str::uuid(), 'id_lokasi' => $this->fip->id_lokasi, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas'],
        ];
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/feeding/batch', ['entries' => $entries], $h)->assertOk()->assertJsonPath('data.ringkasan.dibuat', 2);
        $ulang = $this->postJson('/api/admin/feeding/batch', ['entries' => $entries], $h)->assertOk();

        $this->assertSame(['dibuat' => 0, 'sudah_ada' => 2, 'ditolak' => 0], $ulang->json('data.ringkasan'));
        $this->assertDatabaseCount('pemberian_makan', 2);
    }

    public function test_batch_validasi_bentuk_permintaan(): void
    {
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/feeding/batch', [], $h)->assertStatus(422)->assertJsonValidationErrors('entries');
        $this->postJson('/api/admin/feeding/batch', ['entries' => []], $h)->assertStatus(422);
        $this->postJson('/api/admin/feeding/batch', ['entries' => 'x'], $h)->assertStatus(422);
        $this->postJson('/api/admin/feeding/batch', ['entries' => ['bukan-objek']], $h)->assertStatus(422);

        $terlalu = array_fill(0, 101, ['id_lokasi' => $this->danau->id_lokasi, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas']);
        $this->postJson('/api/admin/feeding/batch', ['entries' => $terlalu], $h)->assertStatus(422)->assertJsonValidationErrors('entries');
        $this->assertDatabaseCount('pemberian_makan', 0);
    }

    // ------------------------------------------------------------------ ubah

    public function test_ubah_status_dan_catatan_serta_audit(): void
    {
        $r = $this->catat($this->hariIni, $this->danau, null, 'Belum');

        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['status_pemberian' => 'Tuntas', 'catatan' => '  Sudah, 70gr  '], $this->asRole('admin'))
            ->assertOk()
            ->assertJsonPath('data.status_pemberian', 'Tuntas')
            ->assertJsonPath('data.catatan', 'Sudah, 70gr')
            ->assertJsonPath('data.tanggal', $this->hariIni);

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'pemberian_makan', 'id_data' => $r->id_pemberian]);
    }

    public function test_ubah_tanggal_ke_kunci_yang_sudah_ada_ditolak_tetapi_nilai_sendiri_boleh(): void
    {
        $this->catat($this->kemarin, $this->danau);
        $r = $this->catat($this->hariIni, $this->danau);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['tanggal' => $this->kemarin], $h)->assertStatus(422)->assertJsonValidationErrors('tanggal');
        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['tanggal' => $this->hariIni, 'catatan' => 'x'], $h)->assertOk();
    }

    public function test_ubah_menerapkan_aturan_tanggal_status_dan_kucing(): void
    {
        $r = $this->catat($this->hariIni, $this->danau);
        $almarhum = $this->buatKucing(['status_kucing' => 'Almarhum']);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['tanggal' => $this->besok], $h)->assertStatus(422)->assertJsonValidationErrors('tanggal');
        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['status_pemberian' => 'Terjadwal'], $h)->assertOk();   // hari ini = boleh
        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['id_kucing' => $almarhum->id_kucing], $h)->assertStatus(422)->assertJsonValidationErrors('id_kucing');
        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['status_pemberian' => 'Mungkin'], $h)->assertStatus(422);
    }

    public function test_catatan_lama_kucing_almarhum_tetap_bisa_dikoreksi_catatannya(): void
    {
        $k = $this->buatKucing(['status_kucing' => 'Terdata']);
        $r = $this->catat($this->kemarin, $this->danau, $k);
        $k->update(['status_kucing' => 'Almarhum']);   // kucing meninggal setelah catatan dibuat

        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['catatan' => 'Koreksi takaran'], $this->asRole('admin'))
            ->assertOk()->assertJsonPath('data.catatan', 'Koreksi takaran');
    }

    public function test_client_uuid_tidak_bisa_diubah_dan_ubah_tidak_ada_404(): void
    {
        $uuid = (string) Str::uuid();
        $r = $this->catat($this->hariIni, $this->danau, null, 'Tuntas', null, $uuid);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/feeding/{$r->id_pemberian}", ['client_uuid' => (string) Str::uuid(), 'catatan' => 'x'], $h)->assertOk();
        $this->assertSame($uuid, $r->fresh()->client_uuid);
        $this->putJson('/api/admin/feeding/99999', ['catatan' => 'x'], $h)->assertStatus(404);
    }

    // ----------------------------------------------------------------- hapus

    public function test_hapus_catatan_dan_audit(): void
    {
        $r = $this->catat($this->hariIni, $this->danau);

        $this->deleteJson("/api/admin/feeding/{$r->id_pemberian}", [], $this->asRole('admin'))->assertOk();

        $this->assertDatabaseMissing('pemberian_makan', ['id_pemberian' => $r->id_pemberian]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'DELETE', 'nama_tabel' => 'pemberian_makan', 'id_data' => $r->id_pemberian]);
        $this->deleteJson('/api/admin/feeding/99999', [], $this->asRole('admin'))->assertStatus(404);
    }

    // ------------------------------------------------------------------ daftar

    public function test_daftar_dengan_filter_dan_paginasi(): void
    {
        $k = $this->buatKucing(['id_lokasi' => $this->danau->id_lokasi]);
        $this->catat('2026-11-10', $this->danau, $k, 'Tuntas');
        $this->catat('2026-11-11', $this->danau, null, 'Belum');
        $this->catat('2026-11-11', $this->fip, null, 'Tuntas');
        $this->catat('2026-11-12', $this->fip, null, 'Tuntas');
        $h = $this->asRole('admin');
        $total = fn (string $q) => $this->getJson("/api/admin/feeding?{$q}", $h)->assertOk()->json('meta.total');

        $this->assertSame(4, $total(''));
        $this->assertSame(2, $total('tanggal=2026-11-11'));
        $this->assertSame(3, $total('dari=2026-11-11'));
        $this->assertSame(2, $total('dari=2026-11-10&sampai=2026-11-11&id_lokasi='.$this->danau->id_lokasi));
        $this->assertSame(2, $total('id_kampus='.$this->lwt->id_kampus));
        $this->assertSame(1, $total('id_kucing='.$k->id_kucing));
        $this->assertSame(1, $total('status=Belum'));
        $this->assertSame(0, $total('tanggal=2026-12-31'));

        $res = $this->getJson('/api/admin/feeding?per_page=3&page=2', $h)->assertOk()
            ->assertJsonPath('meta.halaman', 2)->assertJsonPath('meta.halaman_akhir', 2)->assertJsonCount(1, 'data');
        // urutan: tanggal terbaru dulu
        $semua = collect($this->getJson('/api/admin/feeding', $h)->json('data'))->pluck('tanggal')->all();
        $this->assertSame(['2026-11-12', '2026-11-11', '2026-11-11', '2026-11-10'], $semua);
        $this->assertArrayHasKey('id_pemberian', $res->json('data.0'));
    }

    public function test_filter_daftar_tidak_valid_422(): void
    {
        $h = $this->asRole('admin');

        foreach (['tanggal=besok', 'dari=2026-11-10&sampai=2026-11-01', 'id_lokasi=999', 'id_kucing=999', 'id_kampus=999', 'status=Mungkin', 'per_page=101', 'page=0'] as $q) {
            $this->getJson("/api/admin/feeding?{$q}", $h)->assertStatus(422);
        }
    }

    // ------------------------------------------------------------------ rekap

    private function isiRekap(): void
    {
        $a = $this->buatKucing(['id_lokasi' => $this->danau->id_lokasi]);
        $b = $this->buatKucing(['id_lokasi' => $this->danau->id_lokasi]);
        $c = $this->buatKucing(['id_lokasi' => $this->fip->id_lokasi]);

        $this->catat('2026-11-15', $this->danau, $a, 'Tuntas');   // Minggu sebelum pekan acuan
        $this->catat('2026-11-16', $this->danau, $a, 'Tuntas');   // Senin
        $this->catat('2026-11-16', $this->danau, $b, 'Belum');
        $this->catat('2026-11-17', $this->danau, null, 'Tuntas');
        $this->catat('2026-11-18', $this->fip, $c, 'Tuntas');
        $this->catat('2026-11-18', $this->fip, null, 'Belum');
        $this->catat('2026-11-22', $this->fip, null, 'Tuntas');   // Minggu penutup pekan
        $this->catat('2026-11-23', $this->danau, $a, 'Tuntas');   // Senin pekan berikutnya
    }

    public function test_rekap_mingguan_senin_sampai_minggu(): void
    {
        $this->isiRekap();

        $res = $this->getJson('/api/admin/feeding/rekap?periode=mingguan&tanggal=2026-11-18', $this->asRole('admin'))->assertOk()
            ->assertJsonPath('data.periode', 'mingguan')
            ->assertJsonPath('data.dari', '2026-11-16')
            ->assertJsonPath('data.sampai', '2026-11-22')
            ->assertJsonPath('data.ringkasan.tuntas', 4)
            ->assertJsonPath('data.ringkasan.belum', 2)
            ->assertJsonPath('data.ringkasan.terjadwal', 0)
            ->assertJsonPath('data.ringkasan.total', 6)
            ->assertJsonPath('data.ringkasan.persen_tuntas', 66.7);

        $perHari = collect($res->json('data.per_hari'));
        $this->assertCount(7, $perHari);                                   // semua hari ditampilkan
        $this->assertSame(['tanggal' => '2026-11-16', 'tuntas' => 1, 'belum' => 1, 'terjadwal' => 0], $perHari[0]);
        $this->assertSame(['tanggal' => '2026-11-19', 'tuntas' => 0, 'belum' => 0, 'terjadwal' => 0], $perHari[3]);   // hari tanpa catatan = 0

        $perLokasi = collect($res->json('data.per_lokasi'))->keyBy('nama_lokasi');
        $this->assertSame(3, $perLokasi['Danau']['total']);
        $this->assertSame(2, $perLokasi['Danau']['tuntas']);
        $this->assertSame(3, $perLokasi['FIP']['total']);
        $this->assertSame('Kampus Lidah Wetan', $perLokasi['FIP']['nama_kampus']);
    }

    public function test_rekap_bulanan_dan_filter_lokasi(): void
    {
        $this->isiRekap();
        $h = $this->asRole('admin');

        $this->getJson('/api/admin/feeding/rekap?periode=bulanan&tanggal=2026-11-18', $h)->assertOk()
            ->assertJsonPath('data.dari', '2026-11-01')
            ->assertJsonPath('data.sampai', '2026-11-30')
            ->assertJsonPath('data.ringkasan.total', 8)
            ->assertJsonPath('data.ringkasan.persen_tuntas', 75)
            ->assertJsonCount(30, 'data.per_hari');

        $this->getJson('/api/admin/feeding/rekap?tanggal=2026-11-18&id_lokasi='.$this->danau->id_lokasi, $h)->assertOk()   // periode bawaan: mingguan
            ->assertJsonPath('data.periode', 'mingguan')
            ->assertJsonPath('data.ringkasan.total', 3)
            ->assertJsonCount(1, 'data.per_lokasi');

        $this->getJson('/api/admin/feeding/rekap?tanggal=2026-11-18&id_kampus='.$this->lwt->id_kampus, $h)->assertOk()
            ->assertJsonPath('data.ringkasan.total', 3);
    }

    public function test_rekap_tanpa_data_dan_tanpa_parameter(): void
    {
        $h = $this->asRole('admin');

        $this->getJson('/api/admin/feeding/rekap?tanggal=2026-01-07', $h)->assertOk()
            ->assertJsonPath('data.ringkasan.total', 0)
            ->assertJsonPath('data.ringkasan.persen_tuntas', null)
            ->assertJsonCount(0, 'data.per_lokasi');

        // tanpa parameter: pekan berjalan (menurut WIB)
        $this->catat($this->hariIni, $this->danau);
        $this->getJson('/api/admin/feeding/rekap', $h)->assertOk()->assertJsonPath('data.ringkasan.total', 1);

        $this->getJson('/api/admin/feeding/rekap?periode=tahunan', $h)->assertStatus(422);
        $this->getJson('/api/admin/feeding/rekap?tanggal=kemarin', $h)->assertStatus(422);
        $this->getJson('/api/admin/feeding/rekap?id_lokasi=999', $h)->assertStatus(422);
    }
}

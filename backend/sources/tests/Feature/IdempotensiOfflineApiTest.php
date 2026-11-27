<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FotoKucing;
use App\Models\Kucing;
use App\Models\RiwayatKesehatan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\TestCase;

/**
 * dev-backend-15: tulis dari HP offline harus aman diulang. Riwayat kesehatan (tunggal dan batch) dan unggah foto
 * memakai client_uuid sehingga pengiriman ulang tidak membuat data ganda. Waktu dibekukan 26 Nov 2026 10.00 WIB.
 */
class IdempotensiOfflineApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, RefreshDatabase;

    private ?string $tokenAdmin = null;

    private Kucing $kucing;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));
        $this->siapkanWilayah();
        Storage::fake('kucing_foto');
        $this->kucing = $this->buatKucing(['status_steril' => 'Belum']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): array
    {
        $this->tokenAdmin ??= $this->tokenFor($this->makeUser('admin', 'aktif', 'lapangan@uji.test'));

        return $this->bearer($this->tokenAdmin);
    }

    private function catat(?Kucing $k, array $o = [])
    {
        return $this->postJson('/api/admin/kucing/'.($k ?? $this->kucing)->id_kucing.'/kesehatan', $o + [
            'jenis_tindakan' => 'Pemeriksaan', 'tanggal' => '2026-11-25', 'catatan' => 'Sehat.',
        ], $this->admin());
    }

    private function entri(array $o = []): array
    {
        return $o + ['id_kucing' => $this->kucing->id_kucing, 'jenis_tindakan' => 'Vaksinasi', 'tanggal' => '2026-11-25', 'catatan' => 'Tricat', 'client_uuid' => (string) Str::uuid()];
    }

    private function batch(array $entries)
    {
        return $this->postJson('/api/admin/kesehatan/batch', ['entries' => $entries], $this->admin());
    }

    private function unggah(?Kucing $k, array $o = [])
    {
        return $this->post('/api/admin/kucing/'.($k ?? $this->kucing)->id_kucing.'/foto', $o + ['foto' => UploadedFile::fake()->image('a.jpg', 400, 400)], $this->admin() + ['Accept' => 'application/json']);
    }

    // ------------------------------------------------------------ kesehatan tunggal

    public function test_catatan_kesehatan_yang_dikirim_ulang_tidak_menjadi_ganda(): void
    {
        $uuid = (string) Str::uuid();

        $pertama = $this->catat(null, ['client_uuid' => $uuid])->assertCreated()->assertJsonPath('data.client_uuid', $uuid);
        $kedua = $this->catat(null, ['client_uuid' => $uuid])->assertOk()
            ->assertJsonPath('message', 'Catatan ini sudah tersimpan sebelumnya (pengiriman ulang diabaikan).');

        $this->assertSame($pertama->json('data.id_kesehatan'), $kedua->json('data.id_kesehatan'));
        $this->assertSame(1, RiwayatKesehatan::count());
        $this->assertSame(1, AuditLog::where('nama_tabel', 'riwayat_kesehatan')->count());          // pengiriman ulang tidak dicatat sebagai CREATE
    }

    public function test_tanpa_client_uuid_perilaku_lama_tetap_sama(): void
    {
        $this->catat(null)->assertCreated()->assertJsonPath('data.client_uuid', null);
        $this->catat(null)->assertCreated();                                                      // tanpa UUID: dua catatan terpisah

        $this->assertSame(2, RiwayatKesehatan::count());
    }

    public function test_pengiriman_ulang_sterilisasi_tidak_ditolak_sebagai_duplikat(): void
    {
        $uuid = (string) Str::uuid();
        $steril = ['jenis_tindakan' => 'Sterilisasi', 'client_uuid' => $uuid];

        $this->catat(null, $steril)->assertCreated();
        $this->assertSame('Sudah', $this->kucing->refresh()->status_steril);

        $this->catat(null, $steril)->assertOk();                                                  // replay: bukan "sudah punya sterilisasi"
        $this->catat(null, ['jenis_tindakan' => 'Sterilisasi', 'client_uuid' => (string) Str::uuid()])->assertStatus(422)->assertJsonValidationErrors('jenis_tindakan');

        $this->assertSame(1, RiwayatKesehatan::where('jenis_tindakan', 'Sterilisasi')->count());
        $this->assertSame(1, AuditLog::where('aktivitas', 'SINKRON_STERIL')->count());
    }

    public function test_client_uuid_tidak_boleh_dipakai_catatan_kucing_lain_dan_harus_valid(): void
    {
        $lain = $this->buatKucing();
        $uuid = (string) Str::uuid();
        $this->catat(null, ['client_uuid' => $uuid])->assertCreated();

        $this->catat($lain, ['client_uuid' => $uuid])->assertStatus(422)->assertJsonValidationErrors('client_uuid');
        $this->catat(null, ['client_uuid' => 'bukan-uuid'])->assertStatus(422)->assertJsonValidationErrors('client_uuid');

        $this->assertSame(0, RiwayatKesehatan::where('id_kucing', $lain->id_kucing)->count());
    }

    public function test_mengubah_catatan_tidak_dapat_mengganti_client_uuid(): void
    {
        $uuid = (string) Str::uuid();
        $id = $this->catat(null, ['client_uuid' => $uuid])->assertCreated()->json('data.id_kesehatan');

        $this->putJson("/api/admin/kesehatan/{$id}", ['catatan' => 'Diperbarui', 'client_uuid' => (string) Str::uuid()], $this->admin())->assertOk();

        $this->assertSame($uuid, RiwayatKesehatan::findOrFail($id)->client_uuid);
        $this->catat(null, ['client_uuid' => $uuid])->assertOk();                                  // kunci idempotensi masih utuh
    }

    // ------------------------------------------------------------ kesehatan batch

    public function test_batch_membuat_catatan_mengabaikan_pengiriman_ulang_dan_menolak_yang_salah(): void
    {
        $ada = $this->entri(['jenis_tindakan' => 'Pemeriksaan']);
        $this->batch([$ada])->assertOk()->assertJsonPath('data.ringkasan', ['dibuat' => 1, 'sudah_ada' => 0, 'ditolak' => 0]);

        $res = $this->batch([
            $ada,                                                                                     // pengiriman ulang
            $this->entri(['jenis_tindakan' => 'Obat Cacing']),                                         // baru
            $this->entri(['jenis_tindakan' => 'Terbang']),                                             // jenis salah
            $this->entri(['tanggal' => '2099-01-01']),                                                 // masa depan
            $this->entri(['id_kucing' => 99999]),                                                      // kucing tidak ada
            $this->entri(['jenis_tindakan' => 'Lainnya', 'catatan' => '']),                            // "Lainnya" wajib dijelaskan
            $this->entri(['client_uuid' => 'bukan-uuid']),
            ['jenis_tindakan' => 'Vaksinasi'],                                                         // kurang lengkap
        ])->assertOk()->assertJsonPath('data.ringkasan', ['dibuat' => 1, 'sudah_ada' => 1, 'ditolak' => 6]);

        $hasil = $res->json('data.hasil');
        $this->assertSame(['sudah_ada', 'dibuat', 'ditolak', 'ditolak', 'ditolak', 'ditolak', 'ditolak', 'ditolak'], array_column($hasil, 'status'));
        $this->assertSame(range(0, 7), array_column($hasil, 'index'));
        $this->assertArrayHasKey('jenis_tindakan', $hasil[2]['errors']);
        $this->assertArrayHasKey('tanggal', $hasil[3]['errors']);
        $this->assertArrayHasKey('id_kucing', $hasil[4]['errors']);
        $this->assertArrayHasKey('catatan', $hasil[5]['errors']);
        $this->assertArrayHasKey('client_uuid', $hasil[6]['errors']);
        $this->assertSame($hasil[0]['id_kesehatan'], RiwayatKesehatan::where('client_uuid', $ada['client_uuid'])->value('id_kesehatan'));
        $this->assertSame(2, RiwayatKesehatan::count());                                          // yang ditolak tidak menyisakan data
    }

    public function test_batch_menjaga_aturan_sterilisasi_tunggal_dan_menyinkronkan_status(): void
    {
        $res = $this->batch([
            $this->entri(['jenis_tindakan' => 'Sterilisasi']),
            $this->entri(['jenis_tindakan' => 'Sterilisasi']),                                         // sterilisasi kedua untuk kucing yang sama
        ])->assertOk()->assertJsonPath('data.ringkasan', ['dibuat' => 1, 'sudah_ada' => 0, 'ditolak' => 1]);

        $this->assertArrayHasKey('jenis_tindakan', $res->json('data.hasil.1.errors'));
        $this->assertSame('Sudah', $this->kucing->refresh()->status_steril);
    }

    public function test_batch_untuk_beberapa_kucing_dan_uuid_lintas_kucing_ditolak(): void
    {
        $lain = $this->buatKucing();
        $uuid = (string) Str::uuid();

        $res = $this->batch([
            $this->entri(['client_uuid' => $uuid]),
            $this->entri(['id_kucing' => $lain->id_kucing, 'client_uuid' => $uuid]),                    // UUID sama untuk kucing lain
            $this->entri(['id_kucing' => $lain->id_kucing]),
        ])->assertOk()->assertJsonPath('data.ringkasan', ['dibuat' => 2, 'sudah_ada' => 0, 'ditolak' => 1]);

        $this->assertArrayHasKey('client_uuid', $res->json('data.hasil.1.errors'));
        $this->assertSame(1, RiwayatKesehatan::where('id_kucing', $this->kucing->id_kucing)->count());
        $this->assertSame(1, RiwayatKesehatan::where('id_kucing', $lain->id_kucing)->count());
    }

    public function test_batch_dicatat_sekali_di_audit_log_dan_tidak_bila_tak_ada_perubahan(): void
    {
        $e = $this->entri();
        $this->batch([$e, $this->entri()])->assertOk();
        $this->assertSame(1, AuditLog::where('aktivitas', 'BATCH_CREATE')->where('nama_tabel', 'riwayat_kesehatan')->count());

        $this->batch([$e])->assertOk()->assertJsonPath('data.ringkasan.sudah_ada', 1);                  // semuanya pengiriman ulang
        $this->batch([$this->entri(['jenis_tindakan' => 'Terbang'])])->assertOk();                      // semuanya ditolak
        $this->assertSame(1, AuditLog::where('aktivitas', 'BATCH_CREATE')->count());
    }

    public function test_validasi_bentuk_permintaan_batch(): void
    {
        $this->postJson('/api/admin/kesehatan/batch', [], $this->admin())->assertStatus(422)->assertJsonValidationErrors('entries');
        $this->postJson('/api/admin/kesehatan/batch', ['entries' => []], $this->admin())->assertStatus(422);
        $this->postJson('/api/admin/kesehatan/batch', ['entries' => 'bukan array'], $this->admin())->assertStatus(422);
        $this->postJson('/api/admin/kesehatan/batch', ['entries' => ['bukan objek']], $this->admin())->assertStatus(422);
        $this->batch(array_fill(0, 51, $this->entri()))->assertStatus(422)->assertJsonValidationErrors('entries');   // maks. 50

        $this->assertSame(0, RiwayatKesehatan::count());
        $this->batch(array_map(fn () => $this->entri(), range(1, 50)))->assertOk()->assertJsonPath('data.ringkasan.dibuat', 50);
    }

    // ------------------------------------------------------------ foto

    public function test_unggah_foto_yang_dikirim_ulang_tidak_menjadi_ganda_dan_tidak_diproses_ulang(): void
    {
        $uuid = (string) Str::uuid();

        $pertama = $this->unggah(null, ['client_uuid' => $uuid])->assertCreated()->assertJsonPath('data.client_uuid', $uuid);
        $kedua = $this->unggah(null, ['client_uuid' => $uuid])->assertOk()
            ->assertJsonPath('message', 'Foto ini sudah tersimpan sebelumnya (pengiriman ulang diabaikan).');

        $this->assertSame($pertama->json('data.id_foto_kucing'), $kedua->json('data.id_foto_kucing'));
        $this->assertSame(1, FotoKucing::count());
        $this->assertCount(1, Storage::disk('kucing_foto')->allFiles());                              // berkas tidak diproses dan disimpan dua kali
        $this->assertSame(1, AuditLog::where('nama_tabel', 'foto_kucing')->count());
    }

    public function test_foto_tanpa_client_uuid_tetap_seperti_semula(): void
    {
        $this->unggah(null)->assertCreated()->assertJsonPath('data.client_uuid', null);
        $this->unggah(null)->assertCreated();

        $this->assertSame(2, FotoKucing::count());
    }

    public function test_client_uuid_foto_tidak_boleh_dipakai_kucing_lain_dan_harus_valid(): void
    {
        $lain = $this->buatKucing();
        $uuid = (string) Str::uuid();
        $this->unggah(null, ['client_uuid' => $uuid])->assertCreated();

        $this->unggah($lain, ['client_uuid' => $uuid])->assertStatus(422)->assertJsonValidationErrors('client_uuid');
        $this->unggah(null, ['client_uuid' => 'bukan-uuid'])->assertStatus(422)->assertJsonValidationErrors('client_uuid');

        $this->assertSame(0, FotoKucing::where('id_kucing', $lain->id_kucing)->count());
        $this->assertCount(1, Storage::disk('kucing_foto')->allFiles());
    }

    public function test_pengiriman_ulang_tetap_berhasil_walau_batas_foto_sudah_penuh(): void
    {
        $uuid = (string) Str::uuid();
        $this->unggah(null, ['client_uuid' => $uuid])->assertCreated();
        foreach (range(2, 10) as $i) {
            $this->unggah(null)->assertCreated();
        }
        $this->unggah(null)->assertStatus(422);                                                       // foto ke-11 ditolak

        $this->unggah(null, ['client_uuid' => $uuid])->assertOk();                                    // tetapi replay foto lama tidak ditolak
        $this->assertSame(10, FotoKucing::count());
    }

    public function test_unggah_foto_menaikkan_waktu_ubah_kucing(): void
    {
        $sebelum = $this->kucing->refresh()->updated_at;
        Carbon::setTestNow(Carbon::now()->addMinutes(5));

        $this->unggah(null)->assertCreated();

        $this->assertTrue($this->kucing->refresh()->updated_at->gt($sebelum));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Kucing;
use App\Models\Lokasi;
use App\Models\PemberianMakan;
use App\Models\RiwayatKesehatan;
use App\Models\SyncPenghapusan;
use App\Services\SinkronService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\TestCase;

/**
 * dev-backend-15: penarikan data untuk PWA offline (delta, penghapusan, pemotongan), rotasi token,
 * dan cache HTTP data publik. Waktu dibekukan pada 26 Nov 2026 10.00 WIB (= 03.00 UTC).
 */
class SinkronOfflineApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, RefreshDatabase;

    private ?string $tokenAdmin = null;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));   // sebelum data dibuat: waktu ubah ikut beku
        $this->siapkanWilayah();
        Storage::fake('kucing_foto');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Header admin (satu akun per tes; header segar tiap panggilan agar guard tidak membawa user lama). */
    private function admin(): array
    {
        $this->tokenAdmin ??= $this->tokenFor($this->makeUser('admin', 'aktif', 'lapangan@uji.test'));

        return $this->bearer($this->tokenAdmin);
    }

    private function tarik(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/admin/sync/pull'.($query ? '?'.$query : ''), $this->admin());
    }

    private function majuMenit(int $menit): void
    {
        Carbon::setTestNow(Carbon::now()->addMinutes($menit));
    }

    private function feeding(Kucing $k, string $tanggal, string $status = 'Tuntas'): PemberianMakan
    {
        return PemberianMakan::create([
            'client_uuid' => (string) Str::uuid(), 'id_kucing' => $k->id_kucing, 'id_lokasi' => $k->id_lokasi,
            'tanggal' => $tanggal, 'status_pemberian' => $status,
        ]);
    }

    private function kesehatan(Kucing $k, string $jenis = 'Pemeriksaan'): RiwayatKesehatan
    {
        return RiwayatKesehatan::create(['id_kucing' => $k->id_kucing, 'jenis_tindakan' => $jenis, 'tanggal' => '2026-11-01']);
    }

    // ------------------------------------------------------------ hak akses

    public function test_endpoint_sinkron_hanya_untuk_admin(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/sync/pull')->assertStatus(401);
        $this->postJson('/api/admin/kesehatan/batch', [])->assertStatus(401);

        foreach (['superadmin', 'adopter'] as $role) {
            $h = $this->asRole($role);
            $this->getJson('/api/admin/sync/pull', $h)->assertStatus(403);
            $this->postJson('/api/admin/kesehatan/batch', [], $h)->assertStatus(403);
        }
    }

    // ------------------------------------------------------------ penarikan penuh

    public function test_penarikan_penuh_memuat_semua_data_untuk_dipakai_offline(): void
    {
        $hidup = $this->buatKucing(['status_kucing' => 'Tersedia']);
        $almarhum = $this->buatKucing(['status_kucing' => 'Almarhum']);
        $this->buatFoto($hidup, true);
        $this->feeding($hidup, '2026-11-26');
        $this->feeding($hidup, '2026-11-13');          // dalam jendela 14 hari
        $this->feeding($hidup, '2026-11-11', 'Belum');  // di luar jendela (batas: hari ini - 14 = 12 Nov)
        $this->kesehatan($almarhum);

        $res = $this->tarik()->assertOk()
            ->assertJsonPath('data.penuh', true)->assertJsonPath('data.lengkap', true)
            ->assertJsonPath('data.waktu_server', '2026-11-26T03:00:00Z')
            ->assertJsonPath('data.tanggal_server', '2026-11-26')
            ->assertJsonPath('data.kursor', '2026-11-26T02:59:55Z');                 // dimundurkan 5 detik
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));   // data admin: tidak boleh disimpan cache bersama

        $d = $res->json('data');
        $this->assertSame(['Kampus Ketintang', 'Kampus Lidah Wetan'], array_column($d['kampus'], 'nama_kampus'));
        $this->assertCount(2, $d['lokasi']);
        $this->assertEqualsCanonicalizing([$hidup->id_kucing, $almarhum->id_kucing], array_column($d['kucing'], 'id_kucing'));   // semua status
        $kucing = collect($d['kucing'])->keyBy('id_kucing');
        $this->assertCount(1, $kucing[$hidup->id_kucing]['galeri']);
        $this->assertSame('Danau', $kucing[$hidup->id_kucing]['lokasi']['nama_lokasi']);
        $this->assertCount(1, $d['kesehatan']);
        $this->assertSame(['2026-11-13', '2026-11-26'], collect($d['feeding'])->pluck('tanggal')->sort()->values()->all());
        $this->assertSame(['kucing' => [], 'lokasi' => [], 'kesehatan' => [], 'feeding' => []], $d['terhapus']);
        $this->assertStringNotContainsString('password', $res->getContent());
    }

    public function test_jendela_hari_feeding_dapat_diatur(): void
    {
        $k = $this->buatKucing();
        $this->feeding($k, '2026-11-26');
        $this->feeding($k, '2026-11-20');

        $this->assertCount(1, $this->tarik('hari_feeding=1')->json('data.feeding'));
        $this->assertCount(2, $this->tarik('hari_feeding=7')->json('data.feeding'));
    }

    // ------------------------------------------------------------ delta

    public function test_penarikan_delta_hanya_memuat_yang_berubah_sejak_kursor(): void
    {
        $lama = $this->buatKucing(['nama_kucing' => 'Lama']);
        $berubah = $this->buatKucing(['nama_kucing' => 'Berubah']);
        $this->feeding($lama, '2026-11-26');
        $this->majuMenit(10);                                      // di luar tumpang tindih 5 detik kursor
        $kursor = $this->tarik()->json('data.kursor');

        $this->majuMenit(10);
        $berubah->update(['deskripsi' => 'Diperbarui setelah kursor']);
        $baru = $this->buatKucing(['nama_kucing' => 'Baru']);
        $this->kesehatan($berubah);
        $this->feeding($baru, '2026-11-26');

        $d = $this->tarik('sejak='.$kursor)->assertOk()->assertJsonPath('data.penuh', false)->json('data');

        $this->assertEqualsCanonicalizing(['Berubah', 'Baru'], array_column($d['kucing'], 'nama_kucing'));   // 'Lama' tidak ikut
        $this->assertCount(1, $d['kesehatan']);
        $this->assertCount(1, $d['feeding']);
        $this->assertCount(2, $d['kampus']);                                                                  // kampus kecil: selalu lengkap
        $this->assertSame([], $d['lokasi']);
        $this->assertSame('2026-11-26T03:19:55Z', $d['kursor']);

        // Tumpang tindih 5 detik: baris yang berubah tepat sebelum kursor boleh terbaca lagi (HP menggabungkan menurut id).
        // Setelah lewat dari itu, tidak ada lagi yang berubah, jadi penarikan berikutnya kosong.
        $this->majuMenit(10);
        $kursor2 = $this->tarik('sejak='.$d['kursor'])->json('data.kursor');
        $this->majuMenit(1);
        $this->tarik('sejak='.$kursor2)->assertOk()->assertJsonCount(0, 'data.kucing')->assertJsonCount(0, 'data.feeding')->assertJsonCount(0, 'data.kesehatan');
    }

    public function test_perubahan_foto_menaikkan_waktu_ubah_kucing_agar_ikut_tertarik(): void
    {
        $k = $this->buatKucing();
        $kursor = $this->tarik()->json('data.kursor');
        $h = $this->admin();

        $this->majuMenit(5);
        $this->post("/api/admin/kucing/{$k->id_kucing}/foto", ['foto' => UploadedFile::fake()->image('a.jpg', 400, 400)], $h + ['Accept' => 'application/json'])->assertCreated();
        $d = $this->tarik('sejak='.$kursor)->json('data');
        $this->assertSame([$k->id_kucing], array_column($d['kucing'], 'id_kucing'));
        $this->assertCount(1, $d['kucing'][0]['galeri']);

        $fotoId = $d['kucing'][0]['galeri'][0]['id_foto_kucing'];
        $kursor2 = $d['kursor'];
        $this->majuMenit(5);
        $this->deleteJson("/api/admin/kucing/{$k->id_kucing}/foto/{$fotoId}", [], $this->admin())->assertOk();
        $d2 = $this->tarik('sejak='.$kursor2)->json('data');
        $this->assertSame([$k->id_kucing], array_column($d2['kucing'], 'id_kucing'));
        $this->assertSame([], $d2['kucing'][0]['galeri']);                                                    // galeri diganti utuh: foto terhapus ikut hilang di HP
    }

    // ------------------------------------------------------------ penghapusan

    public function test_data_yang_dihapus_dilaporkan_agar_dibuang_dari_salinan_lokal(): void
    {
        $k = $this->buatKucing();
        $kosong = $this->buatKucing();                                                                       // tanpa riwayat: boleh dihapus
        $feeding = $this->feeding($k, '2026-11-26');
        $kesehatan = $this->kesehatan($k);
        $lokasiKosong = Lokasi::create(['id_kampus' => $this->ktt->id_kampus, 'nama_lokasi' => 'Kosong', 'koordinat_x' => -7.3, 'koordinat_y' => 112.7]);
        $kursor = $this->tarik()->json('data.kursor');

        $this->majuMenit(10);                                      // di luar tumpang tindih 5 detik kursor
        $this->deleteJson("/api/admin/feeding/{$feeding->id_pemberian}", [], $this->admin())->assertOk();
        $this->deleteJson("/api/admin/kesehatan/{$kesehatan->id_kesehatan}", [], $this->admin())->assertOk();
        $this->deleteJson("/api/admin/kucing/{$kosong->id_kucing}", [], $this->admin())->assertOk();
        $this->deleteJson("/api/admin/lokasi/{$lokasiKosong->id_lokasi}", [], $this->admin())->assertOk();

        $d = $this->tarik('sejak='.$kursor)->assertOk()->json('data');

        $this->assertSame(
            ['kucing' => [$kosong->id_kucing], 'lokasi' => [$lokasiKosong->id_lokasi], 'kesehatan' => [$kesehatan->id_kesehatan], 'feeding' => [$feeding->id_pemberian]],
            $d['terhapus'],
        );
        $this->assertNotContains($kosong->id_kucing, array_column($d['kucing'], 'id_kucing'));

        // tumpang tindih 5 detik: penarikan berikutnya langsung setelahnya boleh melaporkan lagi (HP menggabungkan menurut id)...
        $this->majuMenit(2);
        $berikut = $this->tarik('sejak='.$d['kursor'])->json('data');
        // ...tetapi yang sudah lewat dari tumpang tindih tidak dilaporkan lagi
        $this->majuMenit(2);
        $this->assertSame(['kucing' => [], 'lokasi' => [], 'kesehatan' => [], 'feeding' => []], $this->tarik('sejak='.$berikut['kursor'])->json('data.terhapus'));
    }

    public function test_hanya_model_yang_dihapus_meninggalkan_catatan(): void
    {
        $k = $this->buatKucing();
        $f = $this->feeding($k, '2026-11-26');

        $this->assertSame(0, SyncPenghapusan::count());
        $f->delete();

        $this->assertSame([['entitas' => 'pemberian_makan', 'id_data' => $f->id_pemberian]], SyncPenghapusan::get(['entitas', 'id_data'])->toArray());
    }

    public function test_hp_yang_terlalu_lama_tidak_sinkron_mendapat_penarikan_penuh(): void
    {
        $this->buatKucing();
        $lama = Carbon::now()->subDays(61)->utc()->format('Y-m-d\TH:i:s\Z');
        $cukup = Carbon::now()->subDays(59)->utc()->format('Y-m-d\TH:i:s\Z');

        $this->tarik('sejak='.$lama)->assertJsonPath('data.penuh', true)->assertJsonCount(1, 'data.kucing');   // penghapusan lama sudah dibersihkan: tidak bisa dipercaya
        $this->tarik('sejak='.$cukup)->assertJsonPath('data.penuh', false);
    }

    public function test_pembersih_membuang_catatan_penghapusan_yang_kedaluwarsa(): void
    {
        SyncPenghapusan::create(['entitas' => 'kucing', 'id_data' => 1, 'dihapus_pada' => now()->subDays(61)]);
        SyncPenghapusan::create(['entitas' => 'kucing', 'id_data' => 2, 'dihapus_pada' => now()->subDays(59)]);

        $this->artisan('sync:bersihkan')->expectsOutputToContain('dibersihkan: 1')->assertSuccessful();

        $this->assertSame([2], SyncPenghapusan::pluck('id_data')->all());
        $this->assertSame(0, app(SinkronService::class)->bersihkan());
    }

    public function test_pembersih_terdaftar_di_penjadwal(): void
    {
        $perintah = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => (string) $e->command)->implode(' | ');

        $this->assertStringContainsString('sync:bersihkan', $perintah);
    }

    // ------------------------------------------------------------ pemotongan

    public function test_penarikan_besar_dipotong_dan_dapat_dilanjutkan_sampai_lengkap(): void
    {
        config(['sinkron.batas_per_koleksi' => 2]);
        $ids = [];
        foreach (range(1, 5) as $i) {
            $k = $this->buatKucing();
            DB::table('kucing')->where('id_kucing', $k->id_kucing)->update(['updated_at' => sprintf('2026-11-26 02:00:%02d', $i * 10)]);   // waktu ubah berbeda-beda
            $ids[] = $k->id_kucing;
        }

        $terkumpul = [];
        $sejak = null;
        for ($putaran = 1; $putaran <= 10; $putaran++) {
            $d = $this->tarik($sejak ? 'sejak='.$sejak : '')->assertOk()->json('data');
            $this->assertLessThanOrEqual(2, count($d['kucing']));
            $terkumpul = array_merge($terkumpul, array_column($d['kucing'], 'id_kucing'));

            if ($d['lengkap']) {
                break;
            }
            $this->assertNotEquals($sejak, $d['kursor']);          // kursor harus maju, bukan berputar
            $sejak = $d['kursor'];
        }

        $this->assertTrue($d['lengkap']);
        $this->assertEqualsCanonicalizing($ids, array_unique($terkumpul));    // semua kucing akhirnya diterima
        $this->assertGreaterThan(1, $putaran);
    }

    // ------------------------------------------------------------ validasi & kinerja

    public function test_validasi_parameter_penarikan(): void
    {
        foreach ([
            'sejak=kemarin', 'sejak=2026-11-26', 'sejak=2026-11-26T10:00:00', 'sejak=2026-13-45T10:00:00Z',
            'sejak='.str_repeat('a', 41), 'hari_feeding=0', 'hari_feeding=61', 'hari_feeding=abc',
        ] as $salah) {
            $this->tarik($salah)->assertStatus(422);
        }

        // "+" yang tidak di-encode di URL berubah menjadi spasi: dipulihkan
        $this->tarik('sejak=2026-11-26T10:00:00+07:00')->assertOk();
        $this->tarik('sejak=2026-11-26T03:00:00.123Z')->assertOk();
        $this->tarik('sejak=2026-11-26T10:00:00%2B07:00')->assertOk();
    }

    public function test_jumlah_kueri_penarikan_tetap_kecil_walau_data_banyak(): void
    {
        foreach (range(1, 25) as $i) {
            $k = $this->buatKucing();
            $this->buatFoto($k, true);
            $this->feeding($k, '2026-11-26');
            $this->kesehatan($k);
        }
        $this->admin();

        DB::enableQueryLog();
        $this->tarik()->assertOk()->assertJsonCount(25, 'data.kucing');
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(30, $jumlah, "Penarikan memakai {$jumlah} kueri (harus eager load, bukan per baris).");
    }

    public function test_penarikan_tidak_menulis_audit_log(): void
    {
        $this->buatKucing();
        $this->tarik()->assertOk();

        $this->assertSame(0, AuditLog::where('aktivitas', '!=', 'LOGIN')->count());
    }

    // ------------------------------------------------------------ perpanjang token

    public function test_perpanjang_menukar_token_dan_mencabut_yang_lama(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'lapangan@uji.test');
        $lama = $this->tokenFor($user);
        Carbon::setTestNow(Carbon::now()->addHours(10));          // hampir habis (masa berlaku 12 jam)

        $res = $this->postJson('/api/auth/perpanjang', [], $this->bearer($lama))->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')->assertJsonPath('data.user.email', 'lapangan@uji.test');
        $baru = $res->json('data.token');

        $this->assertNotSame($lama, $baru);
        $this->assertSame(Carbon::now()->addMinutes(720)->utc()->format('Y-m-d\TH:i'), Carbon::parse($res->json('data.expires_at'))->utc()->format('Y-m-d\TH:i'));   // masa berlaku dimulai ulang
        $this->getJson('/api/auth/me', $this->bearer($lama))->assertStatus(401);                        // token lama tidak berlaku lagi
        $this->getJson('/api/auth/me', $this->bearer($baru))->assertOk()
            ->assertJsonPath('data.token_kedaluwarsa_pada', fn ($v) => $v !== null);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_perpanjang_menolak_token_kedaluwarsa_tanpa_token_dan_akun_nonaktif(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'lapangan@uji.test');
        $token = $this->tokenFor($user);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/perpanjang')->assertStatus(401);

        PersonalAccessToken::where('tokenable_id', $user->id_user)->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/auth/perpanjang', [], $this->bearer($token))->assertStatus(401);           // harus login ulang

        $segar = $this->tokenFor($user);
        $user->update(['status_akun' => 'nonaktif']);
        $this->postJson('/api/auth/perpanjang', [], $this->bearer($segar))->assertStatus(403);
    }

    public function test_perpanjang_dibatasi_laju_per_akun(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'lapangan@uji.test');
        $token = $this->tokenFor($user);

        foreach (range(1, 20) as $i) {
            $token = $this->postJson('/api/auth/perpanjang', [], $this->bearer($token))->assertOk()->json('data.token');
        }

        $this->postJson('/api/auth/perpanjang', [], $this->bearer($token))->assertStatus(429);
    }

    // ------------------------------------------------------------ cache data publik

    public function test_data_publik_mendapat_etag_dan_dijawab_304_bila_tidak_berubah(): void
    {
        $k = $this->buatKucing(['status_kucing' => 'Tersedia']);
        \App\Models\Artikel::create([
            'id_user' => $this->makeUser('admin', 'aktif', 'p@uji.test')->id_user, 'judul' => 'Judul uji', 'slug' => 'judul-uji', 'kategori' => 'Cerita Kucing',
            'isi' => str_repeat('isi ', 10), 'status_publikasi' => 'PUBLISHED', 'tanggal_dibuat' => '2026-11-01', 'tanggal_diterbitkan' => '2026-11-01',
        ]);

        foreach (['/api/kampus', '/api/lokasi', "/api/lokasi/{$this->danau->id_lokasi}", '/api/kucing', "/api/kucing/{$k->id_kucing}", "/api/kucing/{$k->id_kucing}/kesehatan",
            '/api/artikel', '/api/artikel/featured', '/api/artikel/kategori', '/api/artikel/judul-uji', '/api/info-publik', '/api/peta/persebaran'] as $uri) {
            $res = $this->getJson($uri)->assertOk();
            $etag = $res->headers->get('ETag');

            $this->assertNotEmpty($etag, "{$uri} tanpa ETag");
            $this->assertStringContainsString('public', $res->headers->get('Cache-Control'), $uri);
            $this->assertStringContainsString('max-age=60', $res->headers->get('Cache-Control'), $uri);

            $ulang = $this->getJson($uri, ['If-None-Match' => $etag]);
            $this->assertSame(304, $ulang->getStatusCode(), "{$uri} tidak menjawab 304");
            $this->assertSame('', $ulang->getContent());

            $this->assertSame(200, $this->getJson($uri, ['If-None-Match' => '"basi"'])->getStatusCode());        // ETag tidak cocok -> data penuh
        }
    }

    public function test_etag_berubah_ketika_data_berubah(): void
    {
        $k = $this->buatKucing(['status_kucing' => 'Tersedia', 'nama_kucing' => 'Mochi']);
        $etag = $this->getJson('/api/kucing')->headers->get('ETag');

        $k->update(['nama_kucing' => 'Mochi Baru']);

        $res = $this->getJson('/api/kucing', ['If-None-Match' => $etag])->assertOk();    // 200, bukan 304
        $this->assertNotSame($etag, $res->headers->get('ETag'));
        $this->assertSame('Mochi Baru', $res->json('data.0.nama_kucing'));
    }

    public function test_error_dan_data_pribadi_tidak_di_cache(): void
    {
        $this->getJson('/api/kucing/99999')->assertNotFound();
        $this->assertNull($this->getJson('/api/kucing/99999')->headers->get('ETag'));
        $this->assertStringNotContainsString('max-age=60', (string) $this->getJson('/api/kucing?per_page=0')->assertStatus(422)->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $this->getJson('/api/kucing?per_page=0')->headers->get('Cache-Control'));

        $this->assertStringNotContainsString('public', (string) $this->getJson('/api/adopsi/tracking/MN-TIDAK-ADA-0000')->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $this->getJson('/api/admin/kucing', $this->admin())->assertOk()->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string) $this->getJson('/api/admin/artikel', $this->admin())->headers->get('Cache-Control'));
    }
}

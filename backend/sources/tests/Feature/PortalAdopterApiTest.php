<?php

namespace Tests\Feature;

use App\Models\Adopter;
use App\Models\AuditLog;
use App\Models\LaporanAdopter;
use App\Models\Notifikasi;
use App\Models\PascaAdopsi;
use App\Models\SerahTerima;
use App\Models\User;
use App\Services\PengingatPascaAdopsiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/**
 * dev-backend-17: portal adopter (kucing saya, laporan kondisi) dan kotak masuk laporan untuk admin (BR-13).
 * Waktu dibekukan pada 26 Nov 2026 10.00 WIB.
 */
class PortalAdopterApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, MembuatPengajuan, RefreshDatabase;

    private ?string $tokenAdmin = null;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));
        $this->siapkanWilayah();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): array
    {
        $this->tokenAdmin ??= $this->tokenFor($this->makeUser('admin', 'aktif', 'petugas@uji.test'));

        return $this->bearer($this->tokenAdmin);
    }

    private function adopter(string $email = 'adopter@contoh.id'): User
    {
        return $this->makeUser('adopter', 'aktif', $email);
    }

    /** @var array<string, string> */
    private array $tokenAkun = [];

    /** Header untuk akun ini (login sekali per akun; header segar tiap panggilan agar guard tidak membawa user lama). */
    private function sebagai(User $u): array
    {
        $this->tokenAkun[$u->email] ??= $this->tokenFor($u);

        return $this->bearer($this->tokenAkun[$u->email]);
    }

    /** Adopsi selesai milik $user: [tanggal serah, [[tanggal, kondisi, catatan?], ...]]. */
    private function adopsi(User $user, string $serah = '2026-11-10', array $updates = [], array $kucing = []): SerahTerima
    {
        $adopter = Adopter::firstOrCreate(['id_user' => $user->id_user], ['tanggal_menjadi_adopter' => $serah, 'status_adopter' => 'aktif']);
        $k = $this->buatKucing($kucing + ['status_kucing' => 'Diadopsi']);
        $p = $this->buatPengajuan($k, 'SELESAI', ['id_adopter' => $adopter->id_adopter, 'tanggal_pengajuan' => '2026-11-01', 'email' => $user->email, 'nama_pemohon' => 'Siti Rahasia', 'alamat' => 'Jl. Pribadi 9, Surabaya']);
        $st = SerahTerima::create(['id_pengajuan' => $p->id_pengajuan, 'tanggal_serah_terima' => $serah, 'status_serah_terima' => 'Selesai']);
        foreach ($updates as [$tanggal, $kondisi, $catatan]) {
            PascaAdopsi::create(['id_serah_terima' => $st->id_serah_terima, 'tanggal_update' => $tanggal, 'status_kondisi' => $kondisi, 'catatan' => $catatan ?? null]);
        }

        return $st;
    }

    private function lapor(User $u, SerahTerima $st, array $o = [])
    {
        return $this->postJson("/api/adopter/kucing-saya/{$st->id_serah_terima}/laporan", $o + ['status_kondisi' => 'Sehat', 'catatan' => 'Lincah dan makan lahap.'], $this->sebagai($u));
    }

    // ------------------------------------------------------------ hak akses

    public function test_endpoint_adopter_hanya_untuk_role_adopter(): void
    {
        $u = $this->adopter();
        $st = $this->adopsi($u);
        $rute = [['getJson', '/api/adopter/kucing-saya'], ['getJson', "/api/adopter/kucing-saya/{$st->id_serah_terima}"], ['postJson', "/api/adopter/kucing-saya/{$st->id_serah_terima}/laporan"]];
        $panggil = fn (string $m, string $uri, array $h = []) => $m === 'getJson' ? $this->getJson($uri, $h) : $this->$m($uri, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('admin'))->assertStatus(403);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);
        }
        $this->assertSame(0, LaporanAdopter::count());
    }

    // ------------------------------------------------------------ kucing saya

    public function test_adopter_hanya_melihat_adopsinya_sendiri_tanpa_data_pribadi(): void
    {
        $saya = $this->adopter('saya@contoh.id');
        $lain = $this->adopter('lain@contoh.id');
        $baru = $this->adopsi($saya, '2026-11-20', [['2026-11-22', 'Sehat', 'Aktif.']], ['nama_kucing' => 'Mochi']);
        $lama = $this->adopsi($saya, '2026-06-01', [], ['nama_kucing' => 'Kismis']);
        $this->adopsi($lain, '2026-11-01', [], ['nama_kucing' => 'Milik Orang']);
        $this->buatFoto($baru->pengajuanAdopsi->kucing, true);

        $res = $this->getJson('/api/adopter/kucing-saya', $this->sebagai($saya))->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(['Mochi', 'Kismis'], array_column(array_column($res->json('data'), 'kucing'), 'nama_kucing'));   // terbaru di atas
        $this->assertStringNotContainsString('Milik Orang', $res->getContent());
        foreach (['Siti Rahasia', 'Jl. Pribadi', 'nomor_whatsapp', 'alamat', 'saya@contoh.id'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $res->getContent());
        }
        $this->assertSame(
            ['id_serah_terima', 'nomor_pengajuan', 'tanggal_serah_terima', 'kucing', 'pemantauan', 'laporan_menunggu'],
            array_keys($res->json('data.0')),
        );
        $this->assertStringContainsString('/images/kucing/', $res->json('data.0.kucing.foto_utama'));
        $this->assertSame('aktif', $res->json('data.0.pemantauan.status'));
        $this->assertSame('2026-11-29', $res->json('data.0.pemantauan.jatuh_tempo'));         // pembaruan 22 Nov + 7 hari
        $this->assertSame('Sehat', $res->json('data.0.pemantauan.kondisi_terkini'));
        $this->assertSame('terlambat', $res->json('data.1.pemantauan.status'));               // serah 1 Jun, belum ada kabar, jatuh tempo lewat
        $this->assertSame(['id_kucing', 'kode_kucing', 'nama_kucing', 'foto_utama'], array_keys($res->json('data.0.kucing')));
        unset($lama);
    }

    public function test_adopter_tanpa_adopsi_mendapat_daftar_kosong(): void
    {
        $this->getJson('/api/adopter/kucing-saya', $this->sebagai($this->adopter()))->assertOk()->assertJsonPath('data', []);
    }

    public function test_detail_memuat_riwayat_resmi_dan_laporan_saya_serta_menolak_milik_orang_lain(): void
    {
        $saya = $this->adopter('saya@contoh.id');
        $lain = $this->adopter('lain@contoh.id');
        $st = $this->adopsi($saya, '2026-11-10', [['2026-11-17', 'Perawatan', 'Flu ringan.'], ['2026-11-24', 'Sehat', null]]);
        $milikLain = $this->adopsi($lain);
        $this->lapor($saya, $st, ['status_kondisi' => 'Sehat'])->assertCreated();

        $res = $this->getJson("/api/adopter/kucing-saya/{$st->id_serah_terima}", $this->sebagai($saya))->assertOk();

        $this->assertSame([['tanggal' => '2026-11-24', 'kondisi' => 'Sehat', 'catatan' => null], ['tanggal' => '2026-11-17', 'kondisi' => 'Perawatan', 'catatan' => 'Flu ringan.']], $res->json('data.riwayat_kondisi'));
        $this->assertSame(1, $res->json('data.laporan_menunggu'));
        $this->assertSame('MENUNGGU', $res->json('data.laporan_saya.0.status'));

        // adopsi orang lain: 404, bukan 403 (keberadaannya tidak dibocorkan)
        $this->getJson("/api/adopter/kucing-saya/{$milikLain->id_serah_terima}", $this->sebagai($saya))->assertNotFound();
        $this->getJson('/api/adopter/kucing-saya/99999', $this->sebagai($saya))->assertNotFound();
    }

    // ------------------------------------------------------------ kirim laporan

    public function test_adopter_mengirim_laporan_yang_masuk_kotak_masuk_dan_tercatat_di_audit(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya);

        $res = $this->lapor($saya, $st, ['status_kondisi' => 'Perawatan', 'catatan' => '  Bersin, sudah ke dokter hewan.  '])->assertCreated()
            ->assertJsonPath('data.status', 'MENUNGGU')->assertJsonPath('data.tanggal', '2026-11-26')->assertJsonPath('data.kondisi', 'Perawatan');

        $l = LaporanAdopter::firstOrFail();
        $this->assertSame('Bersin, sudah ke dokter hewan.', $l->catatan);
        $this->assertSame($saya->id_user, $l->id_user);
        $this->assertSame(0, PascaAdopsi::count());                                              // belum menjadi catatan resmi (BR-13)
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'KIRIM_LAPORAN', 'nama_tabel' => 'laporan_adopter', 'id_data' => $res->json('data.id_laporan')]);
        $this->assertSame(1, AuditLog::where('nama_tabel', 'laporan_adopter')->count());
    }

    public function test_validasi_laporan_adopter(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya);

        $this->lapor($saya, $st, ['status_kondisi' => null])->assertStatus(422)->assertJsonValidationErrors('status_kondisi');
        foreach ([
            ['status_kondisi' => 'Bahagia'],
            ['status_kondisi' => 'Sakit', 'catatan' => null],                  // selain Sehat wajib dijelaskan
            ['status_kondisi' => 'Hilang', 'catatan' => '   '],
            ['catatan' => str_repeat('a', 2001)],
            ['catatan' => ['bukan', 'teks']],
        ] as $salah) {
            $this->lapor($saya, $st, $salah)->assertStatus(422);
        }
        $this->lapor($saya, $st, ['status_kondisi' => 'Sehat', 'catatan' => null])->assertCreated();   // Sehat boleh tanpa catatan
        $this->assertSame(1, LaporanAdopter::count());
    }

    public function test_laporan_menunggu_dibatasi_tiga_per_adopsi_dan_dilonggarkan_setelah_ditinjau(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya);

        foreach (range(1, 3) as $i) {
            $this->lapor($saya, $st)->assertCreated();
        }
        $this->lapor($saya, $st)->assertStatus(422)->assertJsonValidationErrors('status_kondisi');

        $id = LaporanAdopter::orderBy('id_laporan')->value('id_laporan');
        $this->postJson("/api/admin/laporan-adopter/{$id}/catat", [], $this->admin())->assertOk();
        $this->lapor($saya, $st)->assertCreated();                                                  // slot kosong lagi
        $this->assertSame(4, LaporanAdopter::count());
    }

    public function test_laporan_dibatasi_laju_harian_dan_ditolak_bila_pemantauan_berakhir(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya);

        foreach (range(1, 10) as $i) {
            $this->lapor($saya, $st)->assertCreated();
            LaporanAdopter::query()->update(['status_laporan' => 'DICATAT']);                        // agar batas 3 menunggu tidak menghalangi
        }
        $this->lapor($saya, $st)->assertStatus(429);

        $lain = $this->adopter('lain@contoh.id');
        $mati = $this->adopsi($lain, '2026-11-01', [['2026-11-05', 'Meninggal', 'Sakit mendadak.']]);
        $this->lapor($lain, $mati)->assertStatus(422)->assertJsonValidationErrors('status_kondisi');
    }

    public function test_adopter_tidak_bisa_melapor_untuk_adopsi_orang_lain(): void
    {
        $saya = $this->adopter('saya@contoh.id');
        $lain = $this->adopter('lain@contoh.id');
        $milikLain = $this->adopsi($lain);

        $this->lapor($saya, $milikLain)->assertNotFound();

        $this->assertSame(0, LaporanAdopter::count());
    }

    // ------------------------------------------------------------ kotak masuk admin

    public function test_admin_melihat_kotak_masuk_dengan_kontak_dan_filter_status(): void
    {
        $a = $this->adopter('a@contoh.id');
        $b = $this->adopter('b@contoh.id');
        $sa = $this->adopsi($a, '2026-11-10', [], ['nama_kucing' => 'Mochi']);
        $sb = $this->adopsi($b, '2026-11-10', [], ['nama_kucing' => 'Kismis']);
        $this->lapor($a, $sa, ['status_kondisi' => 'Sakit', 'catatan' => 'Muntah'])->assertCreated();
        $this->lapor($b, $sb)->assertCreated();
        $dicatat = $this->lapor($b, $sb)->assertCreated()->json('data.id_laporan');
        $this->postJson("/api/admin/laporan-adopter/{$dicatat}/catat", [], $this->admin())->assertOk();

        $menunggu = $this->getJson('/api/admin/laporan-adopter?status=MENUNGGU', $this->admin())->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.jumlah_per_status', ['MENUNGGU' => 2, 'DICATAT' => 1, 'DITOLAK' => 0]);

        $this->assertSame(['Mochi', 'Kismis'], array_column(array_column($menunggu->json('data'), 'kucing'), 'nama_kucing'));   // antrean: terlama dulu
        $this->assertSame('Siti Rahasia', $menunggu->json('data.0.adopter.nama'));                                          // kontak terlihat admin
        $this->assertArrayHasKey('nomor_whatsapp', $menunggu->json('data.0.adopter'));
        $this->getJson('/api/admin/laporan-adopter?status=DICATAT', $this->admin())->assertJsonPath('meta.total', 1);
        $semua = $this->getJson('/api/admin/laporan-adopter', $this->admin())->assertJsonPath('meta.total', 3);
        $this->assertSame($dicatat, $semua->json('data.0.id_laporan') === $dicatat ? $dicatat : null);                       // tanpa filter: terbaru dulu

        foreach (['status=SELESAI', 'per_page=0', 'per_page=101', 'page=0'] as $salah) {
            $this->getJson("/api/admin/laporan-adopter?{$salah}", $this->admin())->assertStatus(422);
        }
    }

    public function test_admin_mencatat_laporan_menjadi_pembaruan_pemantauan_resmi(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya, '2026-11-10');                                                  // jatuh tempo 17 Nov: sudah terlambat
        $id = $this->lapor($saya, $st, ['status_kondisi' => 'Perawatan', 'catatan' => 'Flu ringan.'])->assertCreated()->json('data.id_laporan');

        $res = $this->postJson("/api/admin/laporan-adopter/{$id}/catat", ['catatan_admin' => ' Sudah ditelepon '], $this->admin())->assertOk()
            ->assertJsonPath('data.status_laporan', 'DICATAT')->assertJsonPath('data.catatan_admin', 'Sudah ditelepon');

        $pasca = PascaAdopsi::firstOrFail();
        $this->assertSame($pasca->id_pasca_adopsi, $res->json('data.id_pasca_adopsi'));
        $this->assertSame(['2026-11-26', 'Perawatan', 'Flu ringan.'], [$pasca->tanggal_update->format('Y-m-d'), $pasca->status_kondisi, $pasca->catatan]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CATAT', 'nama_tabel' => 'laporan_adopter', 'id_data' => $id]);

        // jadwal kabar berikutnya bergeser: tidak terlambat lagi
        $kartu = $this->getJson('/api/adopter/kucing-saya', $this->sebagai($saya))->json('data.0.pemantauan');
        $this->assertSame(['aktif', 'Perawatan', '2026-12-03'], [$kartu['status'], $kartu['kondisi_terkini'], $kartu['jatuh_tempo']]);

        // sudah ditinjau: tidak bisa dicatat atau ditolak lagi
        $this->postJson("/api/admin/laporan-adopter/{$id}/catat", [], $this->admin())->assertStatus(422)->assertJsonValidationErrors('status_laporan');
        $this->postJson("/api/admin/laporan-adopter/{$id}/tolak", ['alasan' => 'Sudah ditinjau'], $this->admin())->assertStatus(422);
        $this->assertSame(1, PascaAdopsi::count());
    }

    public function test_admin_menolak_laporan_dengan_alasan_yang_terlihat_adopter(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya);
        $id = $this->lapor($saya, $st)->assertCreated()->json('data.id_laporan');

        $this->postJson("/api/admin/laporan-adopter/{$id}/tolak", [], $this->admin())->assertStatus(422)->assertJsonValidationErrors('alasan');
        $this->postJson("/api/admin/laporan-adopter/{$id}/tolak", ['alasan' => 'abc'], $this->admin())->assertStatus(422);
        $this->postJson("/api/admin/laporan-adopter/{$id}/tolak", ['alasan' => 'Laporan ganda, kabar hari ini sudah kami catat.'], $this->admin())->assertOk()->assertJsonPath('data.status_laporan', 'DITOLAK');

        $detail = $this->getJson("/api/adopter/kucing-saya/{$st->id_serah_terima}", $this->sebagai($saya))->json('data');
        $this->assertSame(['DITOLAK', 'Laporan ganda, kabar hari ini sudah kami catat.'], [$detail['laporan_saya'][0]['status'], $detail['laporan_saya'][0]['alasan_ditolak']]);
        $this->assertSame(0, PascaAdopsi::count());
        $this->assertSame(0, $detail['laporan_menunggu']);
        $this->postJson('/api/admin/laporan-adopter/99999/tolak', ['alasan' => 'Tidak ada laporan ini'], $this->admin())->assertNotFound();
    }

    public function test_laporan_tidak_bisa_dicatat_bila_pemantauan_sudah_berakhir(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya, '2026-11-10');
        $id = $this->lapor($saya, $st, ['status_kondisi' => 'Sehat'])->assertCreated()->json('data.id_laporan');
        PascaAdopsi::create(['id_serah_terima' => $st->id_serah_terima, 'tanggal_update' => '2026-11-25', 'status_kondisi' => 'Meninggal', 'catatan' => 'Dicatat admin lewat jalur lain.']);

        $this->postJson("/api/admin/laporan-adopter/{$id}/catat", [], $this->admin())->assertStatus(422)->assertJsonValidationErrors('status_laporan');

        $this->assertSame('MENUNGGU', LaporanAdopter::findOrFail($id)->status_laporan);   // tetap bisa ditolak admin
    }

    public function test_endpoint_tinjau_hanya_untuk_admin(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya);
        $id = $this->lapor($saya, $st)->assertCreated()->json('data.id_laporan');

        foreach (['superadmin', 'adopter'] as $role) {
            $h = $this->asRole($role);
            $this->getJson('/api/admin/laporan-adopter', $h)->assertStatus(403);
            $this->postJson("/api/admin/laporan-adopter/{$id}/catat", [], $h)->assertStatus(403);
            $this->postJson("/api/admin/laporan-adopter/{$id}/tolak", ['alasan' => 'Tidak berwenang'], $h)->assertStatus(403);
        }
        $this->assertSame('MENUNGGU', LaporanAdopter::findOrFail($id)->status_laporan);
    }

    // ------------------------------------------------------------ dampak ke bagian lain

    public function test_adopter_yang_sudah_melapor_tidak_ditagih_pengingat_dan_dashboard_menghitungnya(): void
    {
        $saya = $this->adopter();
        $st = $this->adopsi($saya, '2026-11-10');                                                  // terlambat: seharusnya diingatkan
        $id = $this->lapor($saya, $st)->assertCreated()->json('data.id_laporan');

        $this->assertSame(0, app(PengingatPascaAdopsiService::class)->jalankan()['pengingat_dibuat']);
        $peringatan = collect($this->getJson('/api/admin/dashboard/stats', $this->admin())->json('data.peringatan'))->keyBy('kode');
        $this->assertSame(1, $peringatan['laporan_adopter_menunggu']['jumlah']);
        $this->assertSame('admin/laporan-adopter?status=MENUNGGU', $peringatan['laporan_adopter_menunggu']['rujukan']);

        $this->postJson("/api/admin/laporan-adopter/{$id}/tolak", ['alasan' => 'Laporan tidak jelas, mohon kirim ulang.'], $this->admin())->assertOk();
        $this->assertSame(0, Notifikasi::count());                                                  // belum ada pengingat sebelumnya
        $this->assertSame(2, app(PengingatPascaAdopsiService::class)->jalankan()['pengingat_dibuat']);   // setelah ditinjau, ditagih lagi
    }
}

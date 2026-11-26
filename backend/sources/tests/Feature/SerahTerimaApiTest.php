<?php

namespace Tests\Feature;

use App\Models\Adopter;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\PengajuanAdopsi;
use App\Models\SerahTerima;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/** dev-backend-09: serah terima, kucing Diadopsi, pembuatan akun adopter otomatis, berkas privat. */
class SerahTerimaApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, MembuatPengajuan, RefreshDatabase;

    private $kucing;
    private string $hariIni;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
        Storage::fake('serah_terima');
        Storage::fake('lampiran');
        $this->hariIni = Carbon::now('Asia/Jakarta')->toDateString();
        $this->kucing = $this->buatKucing(['status_kucing' => 'Proses Screening', 'status_steril' => 'Sudah']);
    }

    /** Pengajuan DISETUJUI untuk kucing yang sedang ditahan (siap diserahterimakan). */
    private function siapSerahTerima(array $o = [], ?object $kucing = null): PengajuanAdopsi
    {
        $p = $this->buatPengajuan($kucing, 'DISETUJUI', $o + ['tanggal_pengajuan' => '2026-11-01']);
        $this->buatScreening($p, 'Lolos');

        return $p;
    }

    private function serahkan(PengajuanAdopsi $p, array $data = [], ?array $h = null)
    {
        return $this->post("/api/admin/adopsi/{$p->id_pengajuan}/serah-terima", $data + ['tanggal_serah_terima' => $this->hariIni],
            ($h ?? $this->asRole('admin')) + ['Accept' => 'application/json']);
    }

    /** Ambil token aktivasi dari tautan di notifikasi terakhir (satu-satunya tempat token berada). */
    private function tokenDariNotifikasi(PengajuanAdopsi $p): array
    {
        $isi = Notifikasi::where('id_pengajuan', $p->id_pengajuan)->whereIn('jenis_notifikasi', ['Serah Terima', 'Aktivasi Akun'])
            ->orderByDesc('id_notifikasi')->value('isi_pesan');
        preg_match('#aktivasi-akun\?([^\s)]+)#', $isi, $m);
        parse_str(html_entity_decode($m[1] ?? ''), $q);

        return $q;
    }

    // ------------------------------------------------------------ hak akses

    public function test_endpoint_serah_terima_hanya_untuk_admin(): void
    {
        $p = $this->siapSerahTerima();
        $rute = [
            ['postJson', "/api/admin/adopsi/{$p->id_pengajuan}/serah-terima"],
            ['postJson', "/api/admin/adopsi/{$p->id_pengajuan}/kirim-ulang-aktivasi"],
            ['getJson', '/api/admin/serah-terima/1'],
            ['getJson', '/api/admin/serah-terima/1/berkas/foto_serah_terima'],
            ['postJson', '/api/admin/serah-terima/1/berkas'],
        ];
        $panggil = fn (string $m, string $u, array $h = []) => $m === 'getJson' ? $this->getJson($u, $h) : $this->$m($u, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);
        }
        $this->assertDatabaseCount('serah_terima', 0);
        $this->assertSame(0, User::where('role', 'adopter')->whereNotNull('id_user')->where('email', $p->email)->count());   // tidak ada akun adopter yang lahir dari percobaan tanpa wewenang
    }

    // ---------------------------------------------------------- alur utama

    public function test_serah_terima_menyelesaikan_adopsi_dan_membuat_akun_adopter_otomatis(): void
    {
        $p = $this->siapSerahTerima(['nama_pemohon' => 'Dewi Anggraini', 'email' => 'dewi@contoh.id']);

        $res = $this->serahkan($p, ['tanggal_serah_terima' => '2026-11-12'])->assertCreated()
            ->assertJsonPath('data.tanggal_serah_terima', '2026-11-12')
            ->assertJsonPath('data.status_serah_terima', 'Selesai')
            ->assertJsonPath('data.kucing.status_kucing', 'Diadopsi')
            ->assertJsonPath('data.pengajuan.status', 'SELESAI')
            ->assertJsonPath('data.efek.akun_adopter', 'dibuat')
            ->assertJsonPath('data.efek.menunggu_aktivasi', true)
            ->assertJsonPath('data.efek.pengajuan_lain_ditutup', 0)
            ->assertJsonPath('data.adopter.akun.status_akun', 'menunggu_aktivasi');

        // MEOW-BR-07: kucing resmi Diadopsi
        $this->assertSame('Diadopsi', $this->kucing->fresh()->status_kucing);
        // pengajuan selesai dan terhubung ke adopter
        $p->refresh();
        $this->assertSame('SELESAI', $p->status_pengajuan);
        $this->assertNotNull($p->id_adopter);
        // MEOW-BR-06: akun dibuat di sini, bukan saat mengajukan
        $user = User::where('email', 'dewi@contoh.id')->first();
        $this->assertSame(['adopter', 'menunggu_aktivasi', 'Dewi Anggraini'], [$user->role, $user->status_akun, $user->nama]);
        $adopter = Adopter::where('id_user', $user->id_user)->first();
        $this->assertSame($adopter->id_adopter, $p->id_adopter);
        $this->assertSame('2026-11-12', $adopter->tanggal_menjadi_adopter->format('Y-m-d'));
        $this->assertSame('aktif', $adopter->status_adopter);
        $this->assertDatabaseHas('serah_terima', ['id_pengajuan' => $p->id_pengajuan, 'status_serah_terima' => 'Selesai']);

        // audit lengkap
        $id = $res->json('data.id_serah_terima');
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'serah_terima', 'id_data' => $id]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'BUAT_AKUN_ADOPTER', 'nama_tabel' => 'users', 'id_data' => $user->id_user]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'SINKRON_STATUS_KUCING', 'nama_tabel' => 'kucing', 'id_data' => $this->kucing->id_kucing]);
    }

    public function test_akun_baru_tidak_bisa_dipakai_login_dan_passwordnya_tidak_diketahui_siapa_pun(): void
    {
        $p = $this->siapSerahTerima(['email' => 'dewi@contoh.id']);
        $this->serahkan($p)->assertCreated();

        $user = User::where('email', 'dewi@contoh.id')->first();
        $this->assertTrue(Hash::isHashed($user->getAttributes()['password_hash']));
        $this->assertStringStartsWith('$2y$', $user->getAttributes()['password_hash']);
        $this->assertFalse($user->isActive());

        foreach (['', 'password', 'Rahasia-123', 'dewi@contoh.id', $p->nomor_whatsapp] as $tebakan) {
            $this->postJson('/api/auth/login', ['email' => 'dewi@contoh.id', 'password' => $tebakan ?: 'x'])->assertStatus(401);
        }
        $this->assertSame(0, $user->tokens()->count());   // tidak ada token API yang terbit untuk akun itu
    }

    public function test_notifikasi_serah_terima_memuat_tautan_aktivasi_tetapi_api_admin_tidak_membocorkan_token(): void
    {
        $p = $this->siapSerahTerima(['nama_pemohon' => 'Dewi Anggraini', 'email' => 'dewi@contoh.id']);
        $admin = $this->asRole('admin');

        $res = $this->serahkan($p, [], $admin)->assertCreated();
        $q = $this->tokenDariNotifikasi($p);

        $notif = Notifikasi::where('id_pengajuan', $p->id_pengajuan)->where('jenis_notifikasi', 'Serah Terima')->get();
        $this->assertSame(['Email', 'WhatsApp'], $notif->pluck('kanal')->sort()->values()->all());
        foreach ($notif as $n) {
            $this->assertSame('PENDING', $n->status_pengiriman);
            $this->assertStringContainsString(rtrim(config('app.frontend_url'), '/').'/aktivasi-akun?', $n->isi_pesan);
            $this->assertStringContainsString('berlaku 7 hari', $n->isi_pesan);
            $this->assertStringContainsString('Dewi', $n->isi_pesan);
        }
        $this->assertSame('dewi@contoh.id', $q['email']);
        $this->assertGreaterThan(30, strlen($q['token']));

        // token tersimpan ter-hash di basis data, tidak sama dengan token di pesan
        $this->assertNotSame($q['token'], DB::table('password_reset_tokens')->where('email', 'dewi@contoh.id')->value('token'));

        // admin tidak pernah melihat token: tidak di respons serah terima, tidak di detail pengajuan, tidak di detail serah terima
        $idSt = $res->json('data.id_serah_terima');
        foreach ([$res->json(), $this->getJson("/api/admin/serah-terima/{$idSt}", $admin)->json(), $this->getJson("/api/admin/adopsi/{$p->id_pengajuan}", $admin)->json()] as $isi) {
            $this->assertStringNotContainsString($q['token'], json_encode($isi));
            $this->assertStringNotContainsString('aktivasi-akun', json_encode($isi));
        }
    }

    // ------------------------------------------------------------ prasyarat

    public function test_hanya_pengajuan_disetujui_yang_bisa_diserahterimakan(): void
    {
        foreach (['MENUNGGU', 'SCREENING', 'DITOLAK', 'SELESAI'] as $status) {
            $kucing = $this->buatKucing(['status_kucing' => 'Proses Screening']);
            $p = $this->buatPengajuan($kucing, $status);
            $this->serahkan($p)->assertStatus(422)->assertJsonValidationErrors('status');
            $this->assertSame('Proses Screening', $kucing->fresh()->status_kucing);
        }
        $this->assertDatabaseCount('serah_terima', 0);
        $this->assertSame(0, User::where('role', 'adopter')->count());   // tidak ada akun adopter yang terbentuk
        $this->assertDatabaseCount('adopter', 0);
    }

    public function test_kucing_harus_berstatus_proses_screening(): void
    {
        foreach (['Tersedia', 'Terdata', 'Hilang', 'Almarhum', 'Diadopsi'] as $status) {
            $kucing = $this->buatKucing(['status_kucing' => $status]);
            $p = $this->siapSerahTerima([], $kucing);
            $this->serahkan($p)->assertStatus(422)->assertJsonValidationErrors('status');
            $this->assertSame($status, $kucing->fresh()->status_kucing);
        }
        $this->assertDatabaseCount('serah_terima', 0);
    }

    public function test_serah_terima_tidak_bisa_dicatat_dua_kali(): void
    {
        $p = $this->siapSerahTerima();
        $this->serahkan($p)->assertCreated();

        $this->serahkan($p)->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('serah_terima', 1);
        $this->assertDatabaseCount('adopter', 1);
    }

    public function test_validasi_tanggal_serah_terima(): void
    {
        $p = $this->siapSerahTerima(['tanggal_pengajuan' => '2026-11-10']);
        $h = $this->asRole('admin');
        $besok = Carbon::now('Asia/Jakarta')->addDay()->toDateString();

        $this->post("/api/admin/adopsi/{$p->id_pengajuan}/serah-terima", [], $h + ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('tanggal_serah_terima');
        foreach (['12-11-2026', 'kemarin', '2019-12-31', $besok, '2026-11-09'] as $salah) {   // terakhir: sebelum tanggal pengajuan
            $this->serahkan($p, ['tanggal_serah_terima' => $salah], $h)->assertStatus(422)->assertJsonValidationErrors('tanggal_serah_terima');
        }
        $this->assertDatabaseCount('serah_terima', 0);
        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);
    }

    public function test_pengajuan_tidak_ada_404_walau_isian_salah(): void
    {
        $this->postJson('/api/admin/adopsi/99999/serah-terima', [], $this->asRole('admin'))->assertStatus(404);
    }

    // ------------------------------------------------------------- akun lama

    public function test_adopter_yang_sudah_punya_akun_dipakai_ulang_tanpa_akun_baru(): void
    {
        $h = $this->asRole('admin');
        $pertama = $this->siapSerahTerima(['email' => 'dewi@contoh.id']);
        $this->serahkan($pertama, [], $h)->assertCreated();
        // akun diaktifkan lebih dulu
        User::where('email', 'dewi@contoh.id')->update(['status_akun' => 'aktif']);

        $kucing2 = $this->buatKucing(['status_kucing' => 'Proses Screening']);
        $kedua = $this->siapSerahTerima(['email' => 'dewi@contoh.id'], $kucing2);
        $this->serahkan($kedua, [], $h)->assertCreated()
            ->assertJsonPath('data.efek.akun_adopter', 'sudah_ada')
            ->assertJsonPath('data.efek.menunggu_aktivasi', false);

        $this->assertSame(1, User::where('email', 'dewi@contoh.id')->count());
        $this->assertSame(1, Adopter::count());
        $this->assertSame($pertama->fresh()->id_adopter, $kedua->fresh()->id_adopter);   // satu adopter, dua kucing

        // tanpa tautan aktivasi karena akun sudah aktif
        $pesan = Notifikasi::where('id_pengajuan', $kedua->id_pengajuan)->where('jenis_notifikasi', 'Serah Terima')->value('isi_pesan');
        $this->assertStringNotContainsString('aktivasi-akun', $pesan);
        $this->assertStringContainsString('akun MeowNesa yang sudah Anda miliki', $pesan);
        $this->assertSame(1, AuditLog::where('aktivitas', 'BUAT_AKUN_ADOPTER')->count());
    }

    public function test_akun_yang_belum_diaktifkan_mendapat_tautan_baru_pada_adopsi_berikutnya(): void
    {
        $h = $this->asRole('admin');
        $pertama = $this->siapSerahTerima(['email' => 'dewi@contoh.id']);
        $this->serahkan($pertama, [], $h)->assertCreated();
        $tokenLama = $this->tokenDariNotifikasi($pertama)['token'];

        $kucing2 = $this->buatKucing(['status_kucing' => 'Proses Screening']);
        $kedua = $this->siapSerahTerima(['email' => 'dewi@contoh.id'], $kucing2);
        $this->serahkan($kedua, [], $h)->assertCreated()->assertJsonPath('data.efek.menunggu_aktivasi', true);

        $tokenBaru = $this->tokenDariNotifikasi($kedua)['token'];
        $this->assertNotSame($tokenLama, $tokenBaru);
        $this->assertSame(1, User::where('email', 'dewi@contoh.id')->count());
    }

    public function test_email_milik_akun_internal_ditolak_dan_tidak_ada_yang_berubah(): void
    {
        $admin = $this->makeUser('admin', 'aktif', 'staf@jagasatwa.test');
        $p = $this->siapSerahTerima(['email' => 'staf@jagasatwa.test']);

        $this->serahkan($p)->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame('DISETUJUI', $p->fresh()->status_pengajuan);
        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);
        $this->assertDatabaseCount('serah_terima', 0);
        $this->assertDatabaseCount('adopter', 0);
        $this->assertSame('admin', $admin->fresh()->role);   // akun internal tidak tersentuh
    }

    // ------------------------------------------------- pengajuan lain ditutup

    public function test_pengajuan_lain_yang_menunggu_untuk_kucing_yang_sama_ditutup_dengan_pemberitahuan(): void
    {
        $lain1 = $this->buatPengajuan(null, 'MENUNGGU', ['nama_pemohon' => 'Citra Lestari']);
        $lain2 = $this->buatPengajuan(null, 'MENUNGGU');
        $kucingLain = $this->buatKucing(['status_kucing' => 'Tersedia']);
        $takTerkait = $this->buatPengajuan($kucingLain, 'MENUNGGU');
        $p = $this->siapSerahTerima();

        $this->serahkan($p)->assertCreated()->assertJsonPath('data.efek.pengajuan_lain_ditutup', 2);

        $this->assertSame(['DITOLAK', 'DITOLAK', 'MENUNGGU'], [$lain1->fresh()->status_pengajuan, $lain2->fresh()->status_pengajuan, $takTerkait->fresh()->status_pengajuan]);
        $pesan = Notifikasi::where('id_pengajuan', $lain1->id_pengajuan)->value('isi_pesan');
        $this->assertStringContainsString('sudah diadopsi oleh pemohon lain', $pesan);
        $this->assertStringContainsString('Citra', $pesan);
        $this->assertSame(0, Notifikasi::where('id_pengajuan', $takTerkait->id_pengajuan)->count());
    }

    // ---------------------------------------------------------------- berkas

    public function test_berkas_disimpan_privat_dengan_nama_acak_dan_tidak_punya_url_publik(): void
    {
        $p = $this->siapSerahTerima();

        $res = $this->serahkan($p, [
            'dokumen_perjanjian' => UploadedFile::fake()->create('bast-asli.pdf', 50, 'application/pdf'),
            'foto_serah_terima'  => UploadedFile::fake()->image('selfie-adopter.jpg', 800, 600),
        ])->assertCreated()
            ->assertJsonPath('data.berkas.dokumen_perjanjian.ada', true)
            ->assertJsonPath('data.berkas.foto_serah_terima.ada', true);

        $st = SerahTerima::first();
        $this->assertMatchesRegularExpression('#^\d+/dokumen_perjanjian-[a-z0-9]{40}\.pdf$#', $st->dokumen_perjanjian);
        $this->assertMatchesRegularExpression('#^\d+/foto_serah_terima-[a-z0-9]{40}\.jpg$#', $st->foto_serah_terima);
        foreach ([$st->dokumen_perjanjian, $st->foto_serah_terima] as $path) {
            Storage::disk('serah_terima')->assertExists($path);
            $this->assertFileDoesNotExist(public_path($path));
            $this->assertStringNotContainsString($path, json_encode($res->json()));   // jalur berkas tak dibocorkan
        }
        $this->assertStringNotContainsString('bast-asli', json_encode($st->getAttributes()));   // nama asli tidak dipakai
    }

    public function test_berkas_tidak_valid_membatalkan_seluruh_serah_terima_tanpa_berkas_yatim(): void
    {
        $p = $this->siapSerahTerima(['email' => 'dewi@contoh.id']);

        $this->serahkan($p, [
            'dokumen_perjanjian' => UploadedFile::fake()->create('bast.pdf', 50, 'application/pdf'),
            'foto_serah_terima'  => UploadedFile::fake()->create('foto.pdf', 50, 'application/pdf'),   // foto tidak boleh PDF
        ])->assertStatus(422)->assertJsonValidationErrors('foto_serah_terima');

        $this->assertDatabaseCount('serah_terima', 0);
        $this->assertDatabaseCount('adopter', 0);
        $this->assertNull(User::where('email', 'dewi@contoh.id')->first());
        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);
        $this->assertSame([], Storage::disk('serah_terima')->allFiles());

        $this->serahkan($p, ['dokumen_perjanjian' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')])->assertStatus(422);
        $this->serahkan($p, ['foto_serah_terima' => UploadedFile::fake()->image('besar.jpg', 800, 600)->size(6000)])->assertStatus(422);
    }

    public function test_admin_mengunduh_berkas_dan_aksesnya_diaudit(): void
    {
        $p = $this->siapSerahTerima();
        $id = $this->serahkan($p, [
            'dokumen_perjanjian' => UploadedFile::fake()->create('bast.pdf', 50, 'application/pdf'),
            'foto_serah_terima'  => UploadedFile::fake()->image('foto.png', 800, 600),
        ])->assertCreated()->json('data.id_serah_terima');
        $admin = $this->asRole('admin');

        $dok = $this->get("/api/admin/serah-terima/{$id}/berkas/dokumen_perjanjian", $admin)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $dok->headers->get('Content-Disposition'));   // PDF: selalu diunduh
        $foto = $this->get("/api/admin/serah-terima/{$id}/berkas/foto_serah_terima", $admin)->assertOk();
        $this->assertStringContainsString('inline', $foto->headers->get('Content-Disposition'));      // gambar: boleh dipratinjau
        $this->assertStringContainsString('no-store', $foto->headers->get('Cache-Control'));

        $this->assertSame(2, AuditLog::where('aktivitas', 'LIHAT_BERKAS_SERAH_TERIMA')->where('id_data', $id)->count());
        $this->get("/api/admin/serah-terima/{$id}/berkas/rahasia", $admin + ['Accept' => 'application/json'])->assertStatus(404);   // jenis di luar daftar
    }

    public function test_unduh_berkas_yang_belum_diunggah_404_dan_tidak_diaudit(): void
    {
        $p = $this->siapSerahTerima();
        $id = $this->serahkan($p)->assertCreated()->assertJsonPath('data.berkas.foto_serah_terima.unduh', null)->json('data.id_serah_terima');

        $this->get("/api/admin/serah-terima/{$id}/berkas/foto_serah_terima", $this->asRole('admin') + ['Accept' => 'application/json'])->assertStatus(404);
        $this->get('/api/admin/serah-terima/99999/berkas/foto_serah_terima', $this->asRole('admin') + ['Accept' => 'application/json'])->assertStatus(404);
        $this->assertSame(0, AuditLog::where('aktivitas', 'LIHAT_BERKAS_SERAH_TERIMA')->count());
    }

    public function test_berkas_bisa_diunggah_belakangan_dan_berkas_lama_dihapus_dari_disk(): void
    {
        $p = $this->siapSerahTerima();
        $id = $this->serahkan($p)->assertCreated()->json('data.id_serah_terima');
        $h = $this->asRole('admin');
        $kirim = fn (string $jenis, $file) => $this->post("/api/admin/serah-terima/{$id}/berkas", ['jenis' => $jenis, 'berkas' => $file], $h + ['Accept' => 'application/json']);

        $kirim('dokumen_perjanjian', UploadedFile::fake()->create('bast-v1.pdf', 20, 'application/pdf'))->assertCreated()
            ->assertJsonPath('data.berkas.dokumen_perjanjian.ada', true);
        $lama = SerahTerima::find($id)->dokumen_perjanjian;
        Storage::disk('serah_terima')->assertExists($lama);

        $kirim('dokumen_perjanjian', UploadedFile::fake()->create('bast-v2.pdf', 20, 'application/pdf'))->assertCreated();
        $baru = SerahTerima::find($id)->dokumen_perjanjian;
        $this->assertNotSame($lama, $baru);
        Storage::disk('serah_terima')->assertMissing($lama);       // berkas yang diganti dibersihkan
        Storage::disk('serah_terima')->assertExists($baru);
        $this->assertCount(1, Storage::disk('serah_terima')->allFiles());
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UNGGAH_BERKAS', 'nama_tabel' => 'serah_terima', 'id_data' => $id]);

        // validasi: foto tidak boleh PDF, jenis harus dikenal, berkas wajib
        $kirim('foto_serah_terima', UploadedFile::fake()->create('foto.pdf', 20, 'application/pdf'))->assertStatus(422)->assertJsonValidationErrors('berkas');
        $kirim('lainnya', UploadedFile::fake()->image('a.jpg', 400, 400))->assertStatus(422)->assertJsonValidationErrors('jenis');
        $this->post("/api/admin/serah-terima/{$id}/berkas", ['jenis' => 'foto_serah_terima'], $h + ['Accept' => 'application/json'])->assertStatus(422);
        $kirim('foto_serah_terima', UploadedFile::fake()->image('foto.jpg', 400, 400))->assertCreated();
    }

    public function test_unggah_berkas_untuk_serah_terima_tidak_ada_404(): void
    {
        $this->post('/api/admin/serah-terima/99999/berkas', [], $this->asRole('admin') + ['Accept' => 'application/json'])->assertStatus(404);
    }

    // ------------------------------------------------------- detail & pelacakan

    public function test_detail_serah_terima_dan_ringkasan_di_detail_pengajuan(): void
    {
        $p = $this->siapSerahTerima(['nama_pemohon' => 'Dewi Anggraini', 'email' => 'dewi@contoh.id']);
        $admin = $this->asRole('admin');
        $id = $this->serahkan($p, ['tanggal_serah_terima' => '2026-11-12'], $admin)->assertCreated()->json('data.id_serah_terima');

        $this->getJson("/api/admin/serah-terima/{$id}", $admin)->assertOk()
            ->assertJsonPath('data.pengajuan.nama_pemohon', 'Dewi Anggraini')
            ->assertJsonPath('data.pengajuan.email', 'dewi@contoh.id')
            ->assertJsonPath('data.kucing.nama_kucing', $this->kucing->nama_kucing)
            ->assertJsonPath('data.adopter.akun.email', 'dewi@contoh.id');
        $this->getJson('/api/admin/serah-terima/99999', $admin)->assertStatus(404);

        $this->getJson("/api/admin/adopsi/{$p->id_pengajuan}", $admin)->assertOk()
            ->assertJsonPath('data.serah_terima.id_serah_terima', $id)
            ->assertJsonPath('data.serah_terima.tanggal_serah_terima', '2026-11-12')
            ->assertJsonPath('data.status_pengajuan', 'SELESAI');
    }

    public function test_pelacakan_publik_menampilkan_adopsi_selesai_dan_kucing_hilang_dari_katalog(): void
    {
        $kode = $this->postJson('/api/adopsi/ajukan', [
            'id_kucing' => ($k = $this->buatKucing(['status_kucing' => 'Tersedia']))->id_kucing, 'nama_pemohon' => 'Dewi Anggraini',
            'nomor_whatsapp' => '081234567890', 'email' => 'dewi@contoh.id', 'alamat' => 'Jl. Ketintang Baru No. 12, Surabaya', 'persetujuan_data' => true,
        ])->assertCreated()->json('data.kode_tracking');
        $p = PengajuanAdopsi::where('kode_tracking', $kode)->first();
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/adopsi/{$p->id_pengajuan}/status", ['status' => 'SCREENING'], $h)->assertOk();
        $this->postJson("/api/admin/adopsi/{$p->id_pengajuan}/screening", [
            'kelayakan_hunian' => 'Baik', 'kestabilan_ekonomi' => 'Stabil', 'pemahaman_perawatan' => 'Paham', 'sikap_proklinik' => 'Bersedia', 'hasil_screening' => 'Lolos',
        ], $h)->assertCreated();
        $this->putJson("/api/admin/adopsi/{$p->id_pengajuan}/status", ['status' => 'DISETUJUI'], $h)->assertOk();
        $this->serahkan($p, [], $h)->assertCreated();

        $res = $this->getJson("/api/adopsi/tracking/{$kode}")->assertOk()
            ->assertJsonPath('data.status', 'SELESAI')
            ->assertJsonPath('data.status_label', 'Adopsi Selesai');
        $this->assertSame([true, true, true, true], collect($res->json('data.tahap'))->pluck('selesai')->all());
        $this->getJson("/api/kucing/{$k->id_kucing}")->assertStatus(404);   // kucing yang sudah diadopsi tidak tampil di katalog

        // serah terima SELESAI tidak bisa dipaksa lewat endpoint status (hanya lewat serah terima)
        $this->putJson("/api/admin/adopsi/{$p->id_pengajuan}/status", ['status' => 'SELESAI'], $h)->assertStatus(422);
    }

    public function test_kucing_diadopsi_tidak_bisa_diberi_makan_dan_riwayatnya_menolak_penghapusan(): void
    {
        $p = $this->siapSerahTerima();
        $this->serahkan($p)->assertCreated();
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/feeding', ['id_kucing' => $this->kucing->id_kucing, 'tanggal' => $this->hariIni, 'status_pemberian' => 'Tuntas'], $h)->assertStatus(422);
        $this->deleteJson("/api/admin/kucing/{$this->kucing->id_kucing}", [], $h)->assertStatus(409);   // punya pengajuan
    }
}

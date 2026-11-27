<?php

namespace Tests\Feature;

use App\Mail\NotifikasiMail;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

/**
 * dev-backend-14: Super Admin - akun pengurus, hak akses, dan penampil audit log (MEOW-F-42 s/d F-44, NF-07).
 * Waktu dibekukan pada 26 Nov 2026 10.00 WIB.
 */
class SuperAdminApiTest extends TestCase
{
    use InteractsWithApiAuth, RefreshDatabase;

    private const PASSWORD_BARU = 'PasswordBaru-123';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private ?string $tokenSa = null;

    /** Header Super Admin (satu akun per tes). Dipanggil per permintaan agar guard tidak membawa user dari permintaan sebelumnya. */
    private function sa(): array
    {
        $this->tokenSa ??= $this->tokenFor($this->makeUser('superadmin', 'aktif', 'sa.utama@uji.test'));

        return $this->bearer($this->tokenSa);
    }

    private function buatAdmin(array $o = [], ?array $h = null)
    {
        return $this->postJson('/api/superadmin/users', $o + ['nama' => 'Relawan Baru', 'email' => 'relawan.baru@jagasatwa.test'], $h ?? $this->sa());
    }

    /** @return array{email: string, token: string} tautan aktivasi dari email terakhir yang dikirim */
    private function tautanDariEmail(): array
    {
        $isi = Mail::sent(NotifikasiMail::class)->last()->isi;
        preg_match('/email=([^&\s]+)&token=([A-Za-z0-9]+)/', $isi, $m);
        $this->assertNotEmpty($m, 'Email tidak memuat tautan aktivasi.');

        return ['email' => urldecode($m[1]), 'token' => $m[2]];
    }

    private function aktifkan(array $tautan, string $password = self::PASSWORD_BARU)
    {
        return $this->postJson('/api/auth/aktivasi', $tautan + ['password' => $password, 'password_confirmation' => $password]);
    }

    // ------------------------------------------------------------ hak akses endpoint

    public function test_semua_endpoint_superadmin_hanya_untuk_super_admin_aktif(): void
    {
        $admin = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $rute = [
            ['getJson', '/api/superadmin/hak-akses'],
            ['getJson', '/api/superadmin/users'],
            ['getJson', "/api/superadmin/users/{$admin->id_user}"],
            ['postJson', '/api/superadmin/users'],
            ['putJson', "/api/superadmin/users/{$admin->id_user}"],
            ['putJson', "/api/superadmin/users/{$admin->id_user}/status"],
            ['postJson', "/api/superadmin/users/{$admin->id_user}/reset-password"],
            ['getJson', '/api/superadmin/audit-logs'],
            ['getJson', '/api/superadmin/audit-logs/opsi'],
        ];
        $panggil = fn (string $m, string $u, array $h = []) => $m === 'getJson' ? $this->getJson($u, $h) : $this->$m($u, [], $h);

        $ditangguhkan = $this->makeUser('superadmin', 'aktif', 'sa.lama@uji.test');
        $tokenLama = $this->tokenFor($ditangguhkan);
        $ditangguhkan->update(['status_akun' => 'nonaktif']);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('admin'))->assertStatus(403);            // admin biasa tidak boleh mengurus akun
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);
            $panggil($metode, $uri, $this->bearer($tokenLama))->assertStatus(403);         // Super Admin yang ditangguhkan kehilangan akses
        }

        $this->assertSame(1, User::where('email', 'staf@uji.test')->count());
        $this->assertSame(0, AuditLog::where('nama_tabel', 'users')->whereIn('aktivitas', ['CREATE', 'UPDATE', 'UBAH_STATUS', 'RESET_PASSWORD'])->count());
    }

    public function test_super_admin_tidak_menjalankan_fungsi_operasional(): void
    {
        $h = $this->sa();

        foreach (['/api/admin/adopsi', '/api/admin/dashboard/stats', '/api/admin/notifikasi', '/api/admin/artikel'] as $uri) {
            $this->getJson($uri, $h)->assertStatus(403);   // memisahkan tugas: Super Admin tidak memutuskan screening/adopsi
        }
    }

    public function test_matriks_hak_akses_per_role_beserta_jumlah_akun(): void
    {
        $this->makeUser('admin', 'aktif', 'a1@uji.test');
        $this->makeUser('admin', 'aktif', 'a2@uji.test');
        $this->makeUser('adopter', 'aktif', 'p1@uji.test');
        $res = $this->getJson('/api/superadmin/hak-akses', $this->sa())->assertOk();

        $baris = collect($res->json('data'))->keyBy('role');
        $this->assertSame(['superadmin', 'admin', 'adopter'], $baris->keys()->all());
        $this->assertSame(User::AKSES['admin'], $baris['admin']['akses']);
        $this->assertSame(User::AKSES['superadmin'], $baris['superadmin']['akses']);
        $this->assertSame('Admin Jaga Satwa', $baris['admin']['role_label']);
        $this->assertSame(2, $baris['admin']['jumlah_akun']);
        $this->assertSame(1, $baris['adopter']['jumlah_akun']);
        $this->assertSame(1, $baris['superadmin']['jumlah_akun']);
        $this->assertNotContains('adopsi.proses', $baris['superadmin']['akses']);
    }

    // ------------------------------------------------------------ daftar & detail akun

    public function test_daftar_akun_dengan_filter_pencarian_dan_ringkasan(): void
    {
        foreach ([['Budi', 'admin', 'aktif', 'budi@jagasatwa.test'], ['Sari', 'admin', 'nonaktif', 'sari@jagasatwa.test'],
                  ['Tunggu', 'admin', 'menunggu_aktivasi', 'tunggu@jagasatwa.test'], ['Adopter Satu', 'adopter', 'aktif', 'adopter@contoh.id']] as [$nama, $role, $status, $email]) {
            User::create(['nama' => $nama, 'email' => $email, 'password_hash' => 'x', 'role' => $role, 'status_akun' => $status]);
        }
        $h = $this->sa();

        $semua = $this->getJson('/api/superadmin/users', $h)->assertOk()->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.jumlah_per_role', ['superadmin' => 1, 'admin' => 3, 'adopter' => 1])
            ->assertJsonPath('meta.jumlah_per_status', ['aktif' => 3, 'nonaktif' => 1, 'menunggu_aktivasi' => 1]);
        $this->assertSame(
            ['id_user', 'nama', 'email', 'role', 'role_label', 'status_akun', 'login_terakhir', 'dibuat_pada'],
            array_keys($semua->json('data.0')),
        );
        $this->assertStringNotContainsString('password', $semua->getContent());
        $this->assertStringNotContainsString('token', $semua->getContent());

        $this->getJson('/api/superadmin/users?role=admin', $h)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.jumlah_per_role.adopter', 1);
        $this->getJson('/api/superadmin/users?status=nonaktif', $h)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.email', 'sari@jagasatwa.test');
        $this->getJson('/api/superadmin/users?q=budi', $h)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/superadmin/users?q=%25', $h)->assertJsonPath('meta.total', 0);                     // % bukan wildcard
        $this->getJson('/api/superadmin/users?role=admin&status=aktif&q=jagasatwa', $h)->assertJsonPath('meta.total', 1);
        $this->assertSame(['adopter@contoh.id', 'budi@jagasatwa.test'], array_column($this->getJson('/api/superadmin/users?sort=nama&per_page=2', $this->sa())->json('data'), 'email'));   // Adopter Satu, Budi
        $this->getJson('/api/superadmin/users?per_page=2&page=3', $h)->assertJsonPath('meta.halaman_akhir', 3)->assertJsonCount(1, 'data');

        foreach (['role=pemilik', 'status=banned', 'per_page=0', 'per_page=101', 'page=0', 'sort=acak'] as $salah) {
            $this->getJson("/api/superadmin/users?{$salah}", $h)->assertStatus(422);
        }
    }

    public function test_login_terakhir_diambil_dari_audit_log_dalam_wib(): void
    {
        $u = $this->makeUser('admin', 'aktif', 'login@uji.test');
        AuditLog::create(['id_user' => $u->id_user, 'aktivitas' => 'LOGIN', 'nama_tabel' => 'users', 'id_data' => $u->id_user, 'waktu_aktivitas' => '2026-11-20 03:00:00']);   // UTC
        AuditLog::create(['id_user' => $u->id_user, 'aktivitas' => 'LOGIN', 'nama_tabel' => 'users', 'id_data' => $u->id_user, 'waktu_aktivitas' => '2026-11-25 17:30:00']);
        AuditLog::create(['id_user' => $u->id_user, 'aktivitas' => 'LOGIN_GAGAL', 'nama_tabel' => 'users', 'id_data' => $u->id_user, 'waktu_aktivitas' => '2026-11-26 01:00:00']);   // bukan login berhasil
        $belum = $this->makeUser('admin', 'aktif', 'belum@uji.test');

        $baris = collect($this->getJson('/api/superadmin/users?per_page=100', $this->sa())->json('data'))->keyBy('email');

        $this->assertSame('2026-11-26T00:30:00+07:00', $baris['login@uji.test']['login_terakhir']);   // 17:30 UTC = 00:30 WIB
        $this->assertNull($baris['belum@uji.test']['login_terakhir']);
        unset($belum);
    }

    public function test_detail_akun_memuat_sesi_aktivitas_dan_status_undangan_tanpa_tautan(): void
    {
        $id = $this->buatAdmin()->assertCreated()->json('data.id_user');
        $admin = User::findOrFail($id);
        AuditLog::create(['id_user' => $id, 'aktivitas' => 'CREATE', 'nama_tabel' => 'artikel', 'id_data' => 5, 'waktu_aktivitas' => now()]);

        $res = $this->getJson("/api/superadmin/users/{$id}", $this->sa())->assertOk()
            ->assertJsonPath('data.status_akun', 'menunggu_aktivasi')
            ->assertJsonPath('data.sesi_aktif', 0)
            ->assertJsonPath('data.undangan_terakhir.jenis', 'Undangan Pengurus')
            ->assertJsonPath('data.undangan_terakhir.status_pengiriman', 'TERKIRIM')
            ->assertJsonPath('data.aktivitas_terakhir.0.nama_tabel', 'artikel');

        $this->assertSame(['jenis', 'status_pengiriman', 'tanggal_dikirim', 'catatan_galat', 'dibuat_pada'], array_keys($res->json('data.undangan_terakhir')));
        $this->assertStringNotContainsString('aktivasi-akun', $res->getContent());
        $this->getJson('/api/superadmin/users/99999', $this->sa())->assertNotFound();
        unset($admin);
    }

    // ------------------------------------------------------------ buat admin

    public function test_membuat_admin_baru_mengirim_tautan_aktivasi_tanpa_pernah_menentukan_password(): void
    {
        $admin = $this->makeUser('superadmin', 'aktif', 'pembuat@uji.test');
        $res = $this->buatAdmin(['nama' => '  Relawan   Baru  ', 'email' => '  Relawan.Baru@JagaSatwa.Test '], $this->bearer($this->tokenFor($admin)))->assertCreated()
            ->assertJsonPath('data.nama', 'Relawan Baru')
            ->assertJsonPath('data.email', 'relawan.baru@jagasatwa.test')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.status_akun', 'menunggu_aktivasi')
            ->assertJsonPath('data.undangan.status_pengiriman', 'TERKIRIM');

        // tautan tidak pernah dikembalikan API; hanya ada di email ke pemiliknya
        $this->assertStringNotContainsString('token', $res->getContent());
        $this->assertStringNotContainsString('aktivasi-akun', $res->getContent());
        Mail::assertSent(NotifikasiMail::class, 1);
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo('relawan.baru@jagasatwa.test') && $m->jenis === 'Undangan Pengurus' && str_contains($m->isi, 'aktivasi-akun?email='));

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'users', 'id_data' => $res->json('data.id_user'), 'id_user' => $admin->id_user]);

        // salinan di basis data tidak menyimpan token setelah terkirim
        $n = Notifikasi::where('id_user', $res->json('data.id_user'))->firstOrFail();
        $this->assertNull($n->id_pengajuan);
        $this->assertSame('Email', $n->kanal);
        $this->assertStringContainsString('token=[disamarkan]', $n->isi_pesan);

        // password awal tidak diketahui siapa pun: tidak bisa login sebelum aktivasi
        $this->postJson('/api/auth/login', ['email' => 'relawan.baru@jagasatwa.test', 'password' => 'admin123'])->assertStatus(401);
    }

    public function test_admin_yang_diundang_mengaktifkan_akun_lalu_bisa_bekerja(): void
    {
        $this->buatAdmin()->assertCreated();
        $tautan = $this->tautanDariEmail();

        $this->aktifkan($tautan + [])->assertOk();
        $this->aktifkan($tautan)->assertStatus(422);                                      // sekali pakai

        $this->assertSame('aktif', User::where('email', 'relawan.baru@jagasatwa.test')->value('status_akun'));
        $token = $this->postJson('/api/auth/login', ['email' => 'relawan.baru@jagasatwa.test', 'password' => self::PASSWORD_BARU])->assertOk()->assertJsonPath('data.user.role', 'admin')->json('data.token');
        $this->getJson('/api/admin/dashboard/stats', $this->bearer($token))->assertOk();   // sudah bisa memakai fungsi admin
        $this->getJson('/api/superadmin/users', $this->bearer($token))->assertStatus(403);
    }

    public function test_validasi_membuat_admin(): void
    {
        $h = $this->sa();
        $this->makeUser('admin', 'aktif', 'sudah@ada.test');

        $this->postJson('/api/superadmin/users', [], $h)->assertStatus(422)->assertJsonValidationErrors(['nama', 'email']);
        foreach ([
            ['nama' => 'A'],
            ['nama' => str_repeat('a', 101)],
            ['email' => 'bukan-email'],
            ['email' => 'sudah@ada.test'],
            ['email' => 'SUDAH@ADA.TEST'],                    // email dibandingkan tanpa peduli huruf besar
            ['email' => str_repeat('a', 150).'@x.test'],
            ['nama' => ['bukan', 'teks']],
        ] as $salah) {
            $this->buatAdmin($salah, $h)->assertStatus(422);
        }

        $this->assertSame(0, User::where('email', 'relawan.baru@jagasatwa.test')->count());
        Mail::assertNothingSent();
        $this->assertSame(0, AuditLog::where('aktivitas', 'CREATE')->where('nama_tabel', 'users')->count());
    }

    public function test_kegagalan_email_undangan_tidak_menggagalkan_pembuatan_akun(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Connection refused'));

        $res = $this->buatAdmin()->assertCreated()
            ->assertJsonPath('data.undangan.status_pengiriman', 'GAGAL')
            ->assertJsonPath('data.undangan.catatan_galat', 'Connection refused');

        $this->assertStringContainsString('gagal dikirim', $res->json('message'));
        $this->assertSame('menunggu_aktivasi', User::where('email', 'relawan.baru@jagasatwa.test')->value('status_akun'));
    }

    public function test_admin_biasa_tidak_bisa_membaca_atau_mengirim_notifikasi_akun_pengurus(): void
    {
        $id = $this->buatAdmin()->assertCreated()->json('data.id_user');
        $n = Notifikasi::where('id_user', $id)->firstOrFail();
        $n->update(['status_pengiriman' => 'PENDING', 'isi_pesan' => 'Atur password: http://x.test/aktivasi-akun?email=a%40b.id&token=RAHASIAUNDANGAN999']);
        $h = $this->asRole('admin');

        // tautan aktivasi akun orang lain tidak boleh bocor lewat endpoint notifikasi admin (bisa dipakai membajak akun)
        $res = $this->getJson('/api/admin/notifikasi', $h)->assertOk()->assertJsonPath('meta.total', 0);
        $this->assertStringNotContainsString('RAHASIAUNDANGAN999', $res->getContent());
        $this->getJson('/api/admin/notifikasi?jenis=Undangan%20Pengurus', $h)->assertJsonPath('meta.total', 0);
        $this->postJson('/api/admin/notifikasi/kirim', ['id_notifikasi' => $n->id_notifikasi], $h)->assertNotFound();
        $this->putJson("/api/admin/notifikasi/{$n->id_notifikasi}/status", ['status' => 'TERKIRIM'], $h)->assertNotFound();
        $this->assertSame(0, $this->getJson('/api/admin/dashboard/stats', $h)->json('data.metrik.antrean_review.nilai'));
        $this->assertStringContainsString('RAHASIAUNDANGAN999', $n->refresh()->isi_pesan);   // tak tersentuh
    }

    // ------------------------------------------------------------ ubah data

    public function test_ubah_nama_dan_email_admin(): void
    {
        $admin = $this->makeUser('admin', 'aktif', 'lama@uji.test');
        $tokenLama = $this->tokenFor($admin);
        
        $this->putJson("/api/superadmin/users/{$admin->id_user}", ['nama' => 'Nama Baru Sekali'], $this->sa())->assertOk()
            ->assertJsonPath('data.nama', 'Nama Baru Sekali')->assertJsonPath('data.email', 'lama@uji.test');
        $this->getJson('/api/auth/me', $this->bearer($tokenLama))->assertOk();            // nama saja tidak mencabut sesi

        $this->putJson("/api/superadmin/users/{$admin->id_user}", ['email' => ' Baru@Uji.Test '], $this->sa())->assertOk()->assertJsonPath('data.email', 'baru@uji.test');
        $this->getJson('/api/auth/me', $this->bearer($tokenLama))->assertStatus(401);     // ganti email = ganti kredensial: sesi lama dicabut
        $this->putJson("/api/superadmin/users/{$admin->id_user}", ['email' => 'baru@uji.test'], $this->sa())->assertOk();   // email sendiri boleh dikirim ulang

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'users', 'id_data' => $admin->id_user]);
        Mail::assertNothingSent();                                                        // akun aktif tidak menerima undangan
    }

    public function test_ganti_email_admin_yang_belum_aktif_mengirim_undangan_ke_email_baru(): void
    {
        $id = $this->buatAdmin(['email' => 'salah.ketik@uji.test'])->assertCreated()->json('data.id_user');
        $tautanLama = $this->tautanDariEmail();

        $this->putJson("/api/superadmin/users/{$id}", ['email' => 'benar@uji.test'], $this->sa())->assertOk()->assertJsonPath('data.undangan.status_pengiriman', 'TERKIRIM');

        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo('benar@uji.test'));
        $this->aktifkan($tautanLama)->assertStatus(422);                                   // tautan ke email lama tidak berlaku lagi
        $this->aktifkan($this->tautanDariEmail())->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'benar@uji.test', 'password' => self::PASSWORD_BARU])->assertOk();
    }

    public function test_validasi_dan_batasan_ubah_akun(): void
    {
        $admin = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $adopter = $this->makeUser('adopter', 'aktif', 'adopter@uji.test');
        $lainnya = $this->makeUser('superadmin', 'aktif', 'sa.lain@uji.test');
        $h = $this->sa();

        foreach ([['nama' => 'A'], ['email' => 'bukan-email'], ['email' => 'adopter@uji.test'], ['nama' => '']] as $salah) {
            $this->putJson("/api/superadmin/users/{$admin->id_user}", $salah, $h)->assertStatus(422);
        }
        $this->putJson("/api/superadmin/users/{$adopter->id_user}", ['nama' => 'Nama Lain'], $h)->assertStatus(422)->assertJsonPath('success', false);   // adopter lewat proses adopsi
        $this->putJson("/api/superadmin/users/{$lainnya->id_user}", ['nama' => 'Nama Lain'], $h)->assertStatus(422);                                    // Super Admin tidak lewat API
        $this->putJson('/api/superadmin/users/99999', ['nama' => 'Nama Lain'], $h)->assertNotFound();

        $this->assertSame('Uji admin', $admin->refresh()->nama);
        $this->assertSame('Uji adopter', $adopter->refresh()->nama);
    }

    // ------------------------------------------------------------ status akun

    public function test_menangguhkan_akun_mengeluarkan_semua_sesi_dan_menolak_login(): void
    {
        $admin = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $token = $this->tokenFor($admin);
        $this->tokenFor($admin);
        $h = $this->sa();

        $this->putJson("/api/superadmin/users/{$admin->id_user}/status", ['status' => 'nonaktif'], $h)->assertOk()->assertJsonPath('data.status_akun', 'nonaktif');

        $this->assertSame(0, $admin->tokens()->count());
        $this->getJson('/api/auth/me', $this->bearer($token))->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD])->assertStatus(403);
        // satu catatan khusus UBAH_STATUS saja (bukan juga UPDATE bawaan)
        $this->assertSame(['UBAH_STATUS'], AuditLog::where('nama_tabel', 'users')->where('id_data', $admin->id_user)->whereIn('aktivitas', ['UBAH_STATUS', 'UPDATE'])->pluck('aktivitas')->all());

        $this->putJson("/api/superadmin/users/{$admin->id_user}/status", ['status' => 'aktif'], $h)->assertOk()->assertJsonPath('data.status_akun', 'aktif');
        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD])->assertOk();
    }

    public function test_status_yang_sama_idempoten_dan_tidak_dicatat_sebagai_perubahan(): void
    {
        $admin = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $sebelum = AuditLog::where('aktivitas', 'UBAH_STATUS')->count();

        $this->putJson("/api/superadmin/users/{$admin->id_user}/status", ['status' => 'aktif'], $this->sa())->assertOk()->assertJsonPath('message', 'Akun sudah berstatus aktif.');

        $this->assertSame($sebelum, AuditLog::where('aktivitas', 'UBAH_STATUS')->count());
    }

    public function test_akun_menunggu_aktivasi_hanya_bisa_dibatalkan_tidak_diaktifkan_paksa(): void
    {
        $id = $this->buatAdmin()->assertCreated()->json('data.id_user');
        $h = $this->sa();

        $this->putJson("/api/superadmin/users/{$id}/status", ['status' => 'aktif'], $h)->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame('menunggu_aktivasi', User::findOrFail($id)->status_akun);

        $tautan = $this->tautanDariEmail();
        $this->putJson("/api/superadmin/users/{$id}/status", ['status' => 'nonaktif'], $h)->assertOk();    // undangan dibatalkan
        $this->aktifkan($tautan)->assertStatus(422);                                                         // tautan lama tidak "menghidupkan" akun yang ditangguhkan
        $this->assertSame('nonaktif', User::findOrFail($id)->status_akun);
    }

    public function test_status_hanya_untuk_admin_dan_adopter_dan_input_valid(): void
    {
        $adopter = $this->makeUser('adopter', 'aktif', 'adopter@uji.test');
        $sa = $this->makeUser('superadmin', 'aktif', 'sa.lain@uji.test');
        $h = $this->sa();

        $this->putJson("/api/superadmin/users/{$adopter->id_user}/status", ['status' => 'nonaktif'], $h)->assertOk();      // adopter boleh ditangguhkan
        $this->putJson("/api/superadmin/users/{$sa->id_user}/status", ['status' => 'nonaktif'], $h)->assertStatus(422);    // Super Admin tidak lewat API
        $this->assertSame('aktif', $sa->refresh()->status_akun);

        foreach ([[], ['status' => 'menunggu_aktivasi'], ['status' => 'banned']] as $salah) {
            $this->putJson("/api/superadmin/users/{$adopter->id_user}/status", $salah, $h)->assertStatus(422)->assertJsonValidationErrors('status');
        }
        $this->putJson('/api/superadmin/users/99999/status', ['status' => 'aktif'], $h)->assertNotFound();
    }

    // ------------------------------------------------------------ reset password

    public function test_reset_password_mencabut_sesi_dan_mengirim_tautan_baru(): void
    {
        $admin = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $token = $this->tokenFor($admin);

        $res = $this->postJson("/api/superadmin/users/{$admin->id_user}/reset-password", [], $this->sa())->assertOk()
            ->assertJsonPath('data.status_akun', 'menunggu_aktivasi')
            ->assertJsonPath('data.undangan.jenis', 'Reset Password')
            ->assertJsonPath('data.undangan.status_pengiriman', 'TERKIRIM');

        $this->assertStringNotContainsString('token', $res->getContent());
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo('staf@uji.test') && $m->jenis === 'Reset Password' && str_contains($m->isi, 'sesi login lama sudah dikeluarkan'));
        $this->getJson('/api/auth/me', $this->bearer($token))->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD])->assertStatus(403);   // belum diatur ulang

        $this->aktifkan($this->tautanDariEmail())->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD])->assertStatus(401);    // password lama mati
        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD_BARU])->assertOk();
        $this->assertSame(['RESET_PASSWORD'], AuditLog::where('nama_tabel', 'users')->where('id_data', $admin->id_user)->whereIn('aktivitas', ['RESET_PASSWORD', 'CREATE', 'UPDATE'])->pluck('aktivitas')->all());
    }

    public function test_reset_untuk_akun_yang_belum_aktif_mengirim_ulang_undangan_dan_mematikan_tautan_lama(): void
    {
        $id = $this->buatAdmin()->assertCreated()->json('data.id_user');
        $lama = $this->tautanDariEmail();

        $this->postJson("/api/superadmin/users/{$id}/reset-password", [], $this->sa())->assertOk()->assertJsonPath('data.undangan.jenis', 'Undangan Pengurus');

        Mail::assertSent(NotifikasiMail::class, 2);
        $this->aktifkan($lama)->assertStatus(422);
        $this->aktifkan($this->tautanDariEmail())->assertOk();
    }

    public function test_reset_password_adopter_dan_penolakannya(): void
    {
        $adopter = $this->makeUser('adopter', 'aktif', 'adopter@contoh.id');
        $nonaktif = $this->makeUser('admin', 'nonaktif', 'mati@uji.test');
        $sa = $this->makeUser('superadmin', 'aktif', 'sa.lain@uji.test');
        $h = $this->sa();

        $this->postJson("/api/superadmin/users/{$adopter->id_user}/reset-password", [], $h)->assertOk();
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo('adopter@contoh.id'));

        $this->postJson("/api/superadmin/users/{$nonaktif->id_user}/reset-password", [], $h)->assertStatus(422);      // aktifkan dulu
        $this->postJson("/api/superadmin/users/{$sa->id_user}/reset-password", [], $h)->assertStatus(422);            // Super Admin tidak lewat API
        $this->postJson('/api/superadmin/users/99999/reset-password', [], $h)->assertNotFound();
        $this->assertSame('nonaktif', $nonaktif->refresh()->status_akun);
        $this->assertSame('aktif', $sa->refresh()->status_akun);
    }

    // ------------------------------------------------------------ audit log

    private function catat(?User $u, string $aktivitas, ?string $tabel, ?int $idData, string $utc): void
    {
        AuditLog::create(['id_user' => $u?->id_user, 'aktivitas' => $aktivitas, 'nama_tabel' => $tabel, 'id_data' => $idData, 'waktu_aktivitas' => $utc]);
    }

    public function test_audit_log_difilter_menurut_tabel_pengguna_aktivitas_dan_data(): void
    {
        $budi = $this->makeUser('admin', 'aktif', 'budi@uji.test');
        $sari = $this->makeUser('admin', 'aktif', 'sari@uji.test');
        $this->sa();
        AuditLog::query()->delete();   // buang baris LOGIN dari pembuatan header
        $this->catat($budi, 'CREATE', 'kucing', 7, '2026-11-20 03:00:00');
        $this->catat($budi, 'UPDATE', 'kucing', 7, '2026-11-21 03:00:00');
        $this->catat($sari, 'CREATE', 'artikel', 3, '2026-11-22 03:00:00');
        $this->catat($sari, 'DELETE', 'kucing', 9, '2026-11-23 03:00:00');
        $this->catat(null, 'LOGIN_GAGAL', 'users', null, '2026-11-24 03:00:00');
        $h = $this->sa();

        $this->getJson('/api/superadmin/audit-logs', $h)->assertOk()->assertJsonPath('meta.total', 5);
        $this->getJson('/api/superadmin/audit-logs?nama_tabel=kucing', $h)->assertJsonPath('meta.total', 3);
        $this->getJson("/api/superadmin/audit-logs?id_user={$budi->id_user}", $h)->assertJsonPath('meta.total', 2);
        $this->getJson('/api/superadmin/audit-logs?aktivitas=CREATE', $h)->assertJsonPath('meta.total', 2);
        $this->getJson('/api/superadmin/audit-logs?nama_tabel=kucing&id_data=7', $h)->assertJsonPath('meta.total', 2);
        $this->getJson("/api/superadmin/audit-logs?nama_tabel=kucing&aktivitas=DELETE&id_user={$sari->id_user}", $h)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id_data', 9);
        $this->getJson('/api/superadmin/audit-logs?nama_tabel=tidak_ada', $h)->assertJsonPath('meta.total', 0)->assertJsonPath('data', []);
    }

    public function test_rentang_tanggal_dibaca_menurut_wib_dan_inklusif(): void
    {
        $this->sa();
        AuditLog::query()->delete();
        $this->catat(null, 'A', 'kucing', 1, '2026-11-24 16:59:59');   // 23:59:59 WIB tgl 24
        $this->catat(null, 'B', 'kucing', 2, '2026-11-24 17:00:00');   // 00:00:00 WIB tgl 25
        $this->catat(null, 'C', 'kucing', 3, '2026-11-25 16:59:59');   // 23:59:59 WIB tgl 25
        $this->catat(null, 'D', 'kucing', 4, '2026-11-25 17:00:00');   // 00:00:00 WIB tgl 26
        $h = $this->sa();

        $hari25 = $this->getJson('/api/superadmin/audit-logs?dari=2026-11-25&sampai=2026-11-25&sort=terlama', $h)->assertOk();
        $this->assertSame(['B', 'C'], array_column($hari25->json('data'), 'aktivitas'));   // tepat satu hari WIB, kedua ujung termasuk
        $this->assertSame(['D'], array_column($this->getJson('/api/superadmin/audit-logs?dari=2026-11-26', $h)->json('data'), 'aktivitas'));
        $this->assertSame(['C', 'B', 'A'], array_column($this->getJson('/api/superadmin/audit-logs?sampai=2026-11-25', $h)->json('data'), 'aktivitas'));

        foreach (['dari=25-11-2026', 'sampai=bukan-tanggal', 'dari=2026-11-26&sampai=2026-11-25', 'dari=2026-13-45'] as $salah) {
            $this->getJson("/api/superadmin/audit-logs?{$salah}", $h)->assertStatus(422);
        }
    }

    public function test_bentuk_baris_audit_urutan_dan_paginasi(): void
    {
        $budi = $this->makeUser('admin', 'aktif', 'budi@uji.test');
        $this->sa();
        AuditLog::query()->delete();
        $this->catat($budi, 'CREATE', 'kucing', 7, '2026-11-20 03:00:00');
        $this->catat(null, 'LOGIN_GAGAL', 'users', null, '2026-11-20 03:00:00');   // waktu sama: id lebih besar di atas
        foreach (range(1, 5) as $i) {
            $this->catat($budi, 'UPDATE', 'kucing', $i, "2026-11-2{$i} 05:00:00");
        }
        $h = $this->sa();

        $res = $this->getJson('/api/superadmin/audit-logs?per_page=3', $h)->assertOk()
            ->assertJsonPath('meta.total', 7)->assertJsonPath('meta.halaman_akhir', 3)->assertJsonCount(3, 'data');
        $this->assertSame([5, 4, 3], array_column($res->json('data'), 'id_data'));                  // terbaru di atas
        $this->assertSame('2026-11-25T12:00:00+07:00', $res->json('data.0.waktu_aktivitas'));        // 05:00 UTC = 12:00 WIB

        $baris = $this->getJson('/api/superadmin/audit-logs?per_page=100&sort=terlama', $h)->json('data');
        $this->assertSame(['LOGIN_GAGAL'], [$baris[1]['aktivitas']]);
        $this->assertNull($baris[1]['pengguna']);                                                    // email tak dikenal: tanpa pengguna
        $this->assertSame(
            ['id_user' => $budi->id_user, 'nama' => 'Uji admin', 'email' => 'budi@uji.test', 'role' => 'admin', 'role_label' => 'Admin Jaga Satwa'],
            $baris[0]['pengguna'],
        );
        $this->assertSame(['id_audit', 'waktu_aktivitas', 'aktivitas', 'nama_tabel', 'id_data', 'pengguna'], array_keys($baris[0]));

        foreach (['per_page=0', 'per_page=101', 'page=0', 'sort=acak', 'id_user=abc', 'id_data=0'] as $salah) {
            $this->getJson("/api/superadmin/audit-logs?{$salah}", $h)->assertStatus(422);
        }
    }

    public function test_opsi_filter_berisi_tabel_dan_aktivitas_yang_pernah_tercatat(): void
    {
        $this->sa();
        AuditLog::query()->delete();
        $this->catat(null, 'UPDATE', 'kucing', 1, '2026-11-20 03:00:00');
        $this->catat(null, 'CREATE', 'artikel', 1, '2026-11-20 03:00:00');
        $this->catat(null, 'CREATE', 'kucing', 2, '2026-11-20 03:00:00');
        $this->catat(null, 'LOGOUT', null, null, '2026-11-20 03:00:00');

        $this->getJson('/api/superadmin/audit-logs/opsi', $this->sa())->assertOk()
            ->assertJsonPath('data.nama_tabel', ['artikel', 'kucing'])
            ->assertJsonPath('data.aktivitas', ['CREATE', 'LOGOUT', 'UPDATE']);
    }

    public function test_audit_log_hanya_bisa_dibaca_tidak_ada_rute_ubah_atau_hapus(): void
    {
        $this->catat(null, 'CREATE', 'kucing', 1, '2026-11-20 03:00:00');
        $id = AuditLog::firstOrFail()->id_audit;
        $h = $this->sa();

        foreach ([['postJson', '/api/superadmin/audit-logs'], ['putJson', "/api/superadmin/audit-logs/{$id}"], ['deleteJson', "/api/superadmin/audit-logs/{$id}"], ['deleteJson', '/api/superadmin/audit-logs']] as [$metode, $uri]) {
            $this->assertContains($this->$metode($uri, [], $h)->getStatusCode(), [404, 405]);
        }
        $this->assertSame(1, AuditLog::where('id_audit', $id)->count());
    }

    public function test_aksi_super_admin_tercatat_dan_dapat_ditelusuri_lewat_audit_log(): void
    {
        $sa = $this->makeUser('superadmin', 'aktif', 'pelaku@uji.test');
        $h = $this->bearer($this->tokenFor($sa));
        $id = $this->buatAdmin([], $h)->assertCreated()->json('data.id_user');
        $this->putJson("/api/superadmin/users/{$id}/status", ['status' => 'nonaktif'], $h)->assertOk();

        $res = $this->getJson("/api/superadmin/audit-logs?nama_tabel=users&id_user={$sa->id_user}&sort=terlama", $h)->assertOk();

        $this->assertSame(['LOGIN', 'CREATE', 'UBAH_STATUS'], array_column($res->json('data'), 'aktivitas'));
        $this->assertSame([$sa->id_user, $id, $id], array_column($res->json('data'), 'id_data'));
    }

    public function test_indeks_audit_log_tersedia_untuk_filter_yang_sering_dipakai(): void
    {
        $nama = collect(Schema::getIndexes('audit_log'))->pluck('name')->all();

        foreach (['audit_waktu_index', 'audit_tabel_waktu_index', 'audit_user_waktu_index', 'audit_aktivitas_waktu_index'] as $indeks) {
            $this->assertContains($indeks, $nama);
        }
        // kueri terfilter memakai indeks (SQLite: SEARCH ... USING INDEX), bukan memindai seluruh tabel
        $rencana = DB::select('EXPLAIN QUERY PLAN select * from audit_log where nama_tabel = ? and waktu_aktivitas >= ? order by waktu_aktivitas desc limit 25', ['kucing', '2026-11-01 00:00:00']);
        $this->assertStringContainsString('INDEX', strtoupper(json_encode($rencana)));
    }
}

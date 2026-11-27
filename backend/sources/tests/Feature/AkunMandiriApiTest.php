<?php

namespace Tests\Feature;

use App\Mail\NotifikasiMail;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

/**
 * dev-backend-16: akun mandiri - ubah password, lupa/reset password lewat email, profil, dan sesi login.
 * Waktu dibekukan pada 26 Nov 2026 10.00 WIB.
 */
class AkunMandiriApiTest extends TestCase
{
    use InteractsWithApiAuth, RefreshDatabase;

    private const PASSWORD_BARU = 'PasswordBaru-123';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));
        Mail::fake();
        $this->withoutDefer();   // pekerjaan "setelah respons" dijalankan langsung agar hasilnya bisa diperiksa
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{email: string, token: string} tautan reset dari email terakhir */
    private function tautanReset(): array
    {
        $isi = Mail::sent(NotifikasiMail::class)->last()->isi;
        preg_match('/reset-password\?email=([^&\s]+)&token=([A-Za-z0-9]+)/', $isi, $m);
        $this->assertNotEmpty($m, 'Email tidak memuat tautan reset.');

        return ['email' => urldecode($m[1]), 'token' => $m[2]];
    }

    private function reset(array $tautan, string $password = self::PASSWORD_BARU)
    {
        return $this->postJson('/api/auth/reset-password', $tautan + ['password' => $password, 'password_confirmation' => $password]);
    }

    private function ubah(string $token, array $o = [], bool $bebasLaju = true)
    {
        if ($bebasLaju) {
            $this->flushRateLimit();   // tes ini bukan tentang pembatasan laju
        }

        return $this->postJson('/api/auth/ubah-password', $o + [
            'password_lama' => self::PASSWORD, 'password' => self::PASSWORD_BARU, 'password_confirmation' => self::PASSWORD_BARU,
        ], $this->bearer($token));
    }

    // ------------------------------------------------------------ ubah password

    public function test_ubah_password_mengeluarkan_sesi_lain_tetapi_sesi_ini_tetap_hidup(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $ini = $this->tokenFor($user);
        $hp = $this->tokenFor($user);
        $laptop = $this->tokenFor($user);

        $this->ubah($ini)->assertOk();

        $this->getJson('/api/auth/me', $this->bearer($ini))->assertOk();                     // sesi yang dipakai mengubah tetap hidup
        $this->getJson('/api/auth/me', $this->bearer($hp))->assertStatus(401);
        $this->getJson('/api/auth/me', $this->bearer($laptop))->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD])->assertStatus(401);   // password lama mati
        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD_BARU])->assertOk();
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UBAH_PASSWORD', 'id_user' => $user->id_user]);
    }

    public function test_ubah_password_menolak_password_lama_salah_dan_input_lemah(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $token = $this->tokenFor($user);

        $this->ubah($token, ['password_lama' => 'salah-total-1'])->assertStatus(422)->assertJsonValidationErrors('password_lama');
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UBAH_PASSWORD_GAGAL', 'id_user' => $user->id_user]);

        foreach ([
            ['password' => 'pendek1', 'password_confirmation' => 'pendek1'],
            ['password' => 'hanyahuruf', 'password_confirmation' => 'hanyahuruf'],
            ['password' => '1234567890', 'password_confirmation' => '1234567890'],
            ['password' => self::PASSWORD_BARU, 'password_confirmation' => 'beda-12345'],
            ['password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD],                  // sama dengan yang lama
            ['password' => 'staf@uji.test', 'password_confirmation' => 'staf@uji.test'],               // sama dengan email
            ['password' => str_repeat('a1', 33), 'password_confirmation' => str_repeat('a1', 33)],     // 66 karakter
            ['password_lama' => ''],
        ] as $salah) {
            $this->ubah($token, $salah)->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD])->assertOk();   // tidak ada yang berubah
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/ubah-password', [])->assertStatus(401);
    }

    public function test_ubah_password_dibatasi_laju_dan_ditolak_untuk_akun_ditangguhkan(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $token = $this->tokenFor($user);

        foreach (range(1, 5) as $i) {
            $this->ubah($token, ['password_lama' => 'salah-total-1'], bebasLaju: false)->assertStatus(422);
        }
        $this->ubah($token, ['password_lama' => 'salah-total-1'], bebasLaju: false)->assertStatus(429);

        $lain = $this->makeUser('admin', 'aktif', 'lain@uji.test');
        $t2 = $this->tokenFor($lain);
        $lain->update(['status_akun' => 'nonaktif']);
        $this->ubah($t2)->assertStatus(403);
    }

    // ------------------------------------------------------------ lupa password

    public function test_lupa_password_mengirim_tautan_dan_reset_berhasil(): void
    {
        $user = $this->makeUser('adopter', 'aktif', 'adopter@contoh.id');
        $lama = $this->tokenFor($user);

        $this->postJson('/api/auth/lupa-password', ['email' => ' Adopter@Contoh.ID '])->assertOk()->assertJsonPath('success', true);

        Mail::assertSent(NotifikasiMail::class, 1);
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo('adopter@contoh.id') && $m->jenis === 'Lupa Password' && str_contains($m->isi, '60 menit'));
        $tautan = $this->tautanReset();
        $this->assertSame('adopter@contoh.id', $tautan['email']);

        $this->reset($tautan)->assertOk();

        $this->getJson('/api/auth/me', $this->bearer($lama))->assertStatus(401);                  // semua sesi lama dicabut
        $this->postJson('/api/auth/login', ['email' => 'adopter@contoh.id', 'password' => self::PASSWORD])->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'adopter@contoh.id', 'password' => self::PASSWORD_BARU])->assertOk();
        $this->reset($tautan)->assertStatus(422);                                                  // sekali pakai
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'RESET_PASSWORD_MANDIRI', 'id_user' => $user->id_user]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'LUPA_PASSWORD_DIMINTA', 'id_user' => $user->id_user]);
    }

    public function test_respons_lupa_password_sama_untuk_semua_email_agar_tidak_membocorkan_akun(): void
    {
        $this->makeUser('admin', 'aktif', 'aktif@uji.test');
        $this->makeUser('admin', 'nonaktif', 'tangguh@uji.test');
        $this->makeUser('admin', 'menunggu_aktivasi', 'tunggu@uji.test');

        $respons = [];
        foreach (['aktif@uji.test', 'tangguh@uji.test', 'tunggu@uji.test', 'tidak.ada@uji.test'] as $email) {
            $res = $this->postJson('/api/auth/lupa-password', ['email' => $email]);
            $respons[] = [$res->getStatusCode(), $res->getContent()];
            $this->flushRateLimit();
        }

        $this->assertCount(1, array_unique(array_map('json_encode', $respons)));                  // persis sama: status dan isi
        Mail::assertSent(NotifikasiMail::class, 1);                                                // hanya akun aktif yang menerima email
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo('aktif@uji.test'));
        $this->assertSame(1, Notifikasi::where('jenis_notifikasi', 'Lupa Password')->count());
    }

    private function flushRateLimit(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_lupa_password_hanya_memeriksa_bentuk_email_dan_dibatasi_laju(): void
    {
        foreach ([[], ['email' => 'bukan-email'], ['email' => str_repeat('a', 150).'@x.test'], ['email' => ['a@b.c']]] as $salah) {
            $this->postJson('/api/auth/lupa-password', $salah)->assertStatus(422);
        }

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/auth/lupa-password', ['email' => 'siapa@uji.test'])->assertOk();
        }
        $this->postJson('/api/auth/lupa-password', ['email' => 'siapa@uji.test'])->assertStatus(429);
    }

    public function test_email_bukan_teks_ditolak_validasi_bukan_galat_server(): void
    {
        // Regresi: pembatas laju pernah melempar 500 ("Array to string conversion") untuk email[]=x di login dan lupa password.
        foreach (['/api/auth/login', '/api/auth/lupa-password', '/api/auth/reset-password', '/api/auth/aktivasi'] as $uri) {
            $this->flushRateLimit();
            $this->postJson($uri, ['email' => ['a@b.c'], 'password' => 'x', 'token' => 'x'])->assertStatus(422);
        }
    }

    public function test_permintaan_berulang_dalam_semenit_tidak_membanjiri_kotak_masuk(): void
    {
        $this->makeUser('admin', 'aktif', 'staf@uji.test');

        $this->postJson('/api/auth/lupa-password', ['email' => 'staf@uji.test'])->assertOk();
        $this->postJson('/api/auth/lupa-password', ['email' => 'staf@uji.test'])->assertOk();

        Mail::assertSent(NotifikasiMail::class, 1);                                                // permintaan kedua dalam 60 detik diabaikan diam-diam
        $this->assertSame(1, Notifikasi::where('jenis_notifikasi', 'Lupa Password')->count());
    }

    public function test_tautan_reset_hanya_ada_di_email_dan_disamarkan_setelah_terkirim(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $res = $this->postJson('/api/auth/lupa-password', ['email' => 'staf@uji.test'])->assertOk();
        $token = $this->tautanReset()['token'];

        $this->assertStringNotContainsString($token, $res->getContent());
        $n = Notifikasi::where('id_user', $user->id_user)->firstOrFail();
        $this->assertSame('TERKIRIM', $n->status_pengiriman);
        $this->assertStringNotContainsString($token, $n->isi_pesan);
        $this->assertStringContainsString('token=[disamarkan]', $n->isi_pesan);

        // admin biasa tidak bisa membaca/mengirim notifikasi ini
        $this->getJson('/api/admin/notifikasi', $this->asRole('admin'))->assertOk()->assertJsonPath('meta.total', 0);
    }

    // ------------------------------------------------------------ reset password

    public function test_reset_menolak_semua_kegagalan_dengan_pesan_yang_sama(): void
    {
        $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $this->makeUser('admin', 'menunggu_aktivasi', 'tunggu@uji.test');
        $this->postJson('/api/auth/lupa-password', ['email' => 'staf@uji.test'])->assertOk();
        $tautan = $this->tautanReset();

        $kegagalan = [
            $this->reset(['email' => 'staf@uji.test', 'token' => 'token-salah']),
            $this->reset(['email' => 'tidak.ada@uji.test', 'token' => $tautan['token']]),
            $this->reset(['email' => 'tunggu@uji.test', 'token' => $tautan['token']]),                  // akun belum aktif
        ];
        foreach ($kegagalan as $res) {
            $res->assertStatus(422)->assertJsonValidationErrors('token');
        }
        $this->assertCount(1, array_unique(array_map(fn ($r) => $r->json('errors.token.0'), $kegagalan)));

        $this->reset($tautan)->assertOk();                                                              // tautan asli tetap berlaku sesudah percobaan gagal
    }

    public function test_tautan_reset_kedaluwarsa_setelah_60_menit_dan_password_lemah_tidak_menghabiskan_token(): void
    {
        $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $this->postJson('/api/auth/lupa-password', ['email' => 'staf@uji.test'])->assertOk();
        $tautan = $this->tautanReset();

        $this->reset($tautan, 'lemah')->assertStatus(422)->assertJsonValidationErrors('password');   // ditolak validasi, token belum terpakai
        $this->reset($tautan, self::PASSWORD_BARU.'x', )->assertOk();

        $this->postJson('/api/auth/lupa-password', ['email' => 'staf@uji.test'])->assertOk();
        Carbon::setTestNow(Carbon::now()->addMinutes(61));
        $this->reset($this->tautanReset())->assertStatus(422);                                           // lewat 60 menit
    }

    public function test_reset_untuk_akun_yang_ditangguhkan_ditolak_dan_super_admin_boleh_mandiri(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $this->postJson('/api/auth/lupa-password', ['email' => 'staf@uji.test'])->assertOk();
        $tautan = $this->tautanReset();
        $user->update(['status_akun' => 'nonaktif']);

        $this->reset($tautan)->assertStatus(422);

        $sa = $this->makeUser('superadmin', 'aktif', 'sa@uji.test');
        $this->flushRateLimit();
        $this->postJson('/api/auth/lupa-password', ['email' => 'sa@uji.test'])->assertOk();
        $this->reset($this->tautanReset())->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'sa@uji.test', 'password' => self::PASSWORD_BARU])->assertOk();
        unset($sa);
    }

    // ------------------------------------------------------------ profil & sesi

    public function test_ubah_nama_sendiri_dan_tidak_bisa_mengubah_email_atau_role(): void
    {
        $user = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $token = $this->tokenFor($user);

        $this->putJson('/api/auth/profil', ['nama' => '  Budi   Santoso  ', 'email' => 'lain@uji.test', 'role' => 'superadmin'], $this->bearer($token))->assertOk()
            ->assertJsonPath('data.nama', 'Budi Santoso')->assertJsonPath('data.email', 'staf@uji.test')->assertJsonPath('data.role', 'admin');

        $user->refresh();
        $this->assertSame(['Budi Santoso', 'staf@uji.test', 'admin'], [$user->nama, $user->email, $user->role]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'users', 'id_data' => $user->id_user]);

        foreach ([[], ['nama' => 'A'], ['nama' => str_repeat('a', 101)], ['nama' => ['x']]] as $salah) {
            $this->putJson('/api/auth/profil', $salah, $this->bearer($token))->assertStatus(422);
        }
    }

    public function test_daftar_sesi_menampilkan_perangkat_dan_menandai_sesi_ini(): void
    {
        $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $hp = $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD, 'perangkat' => '  HP Relawan Ketintang '])->assertOk()->json('data.token');
        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD])->assertOk();
        $kedaluwarsa = $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD])->assertOk()->json('data.token');
        PersonalAccessToken::findToken($kedaluwarsa)->update(['expires_at' => now()->subMinute()]);

        $sesi = collect($this->getJson('/api/auth/sesi', $this->bearer($hp))->assertOk()->json('data'));

        $this->assertCount(2, $sesi);                                                                      // yang kedaluwarsa tidak ditampilkan
        $this->assertSame(['HP Relawan Ketintang', 'api'], $sesi->sortByDesc('saat_ini')->pluck('perangkat')->all());
        $this->assertSame(1, $sesi->where('saat_ini', true)->count());
        $this->assertSame(['id_sesi', 'perangkat', 'dibuat_pada', 'terakhir_dipakai', 'kedaluwarsa_pada', 'saat_ini'], array_keys($sesi->first()));
        $this->assertStringNotContainsString('token', json_encode($sesi));                                 // tidak pernah membocorkan token

        $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD, 'perangkat' => str_repeat('a', 61)])->assertStatus(422);
    }

    public function test_keluarkan_satu_sesi_hanya_milik_sendiri(): void
    {
        $saya = $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $orang = $this->makeUser('admin', 'aktif', 'orang@uji.test');
        $ini = $this->tokenFor($saya);
        $hp = $this->tokenFor($saya);
        $tokenOrang = $this->tokenFor($orang);
        $idHp = PersonalAccessToken::findToken($hp)->id;
        $idOrang = PersonalAccessToken::findToken($tokenOrang)->id;

        $this->deleteJson("/api/auth/sesi/{$idOrang}", [], $this->bearer($ini))->assertNotFound();      // sesi orang lain: tidak bisa disentuh (IDOR)
        $this->getJson('/api/auth/me', $this->bearer($tokenOrang))->assertOk();

        $this->deleteJson("/api/auth/sesi/{$idHp}", [], $this->bearer($ini))->assertOk();
        $this->getJson('/api/auth/me', $this->bearer($hp))->assertStatus(401);
        $this->deleteJson("/api/auth/sesi/{$idHp}", [], $this->bearer($ini))->assertNotFound();
        $this->deleteJson('/api/auth/sesi/99999', [], $this->bearer($ini))->assertNotFound();

        $idIni = PersonalAccessToken::findToken($ini)->id;
        $this->deleteJson("/api/auth/sesi/{$idIni}", [], $this->bearer($ini))->assertOk();               // mengeluarkan sesi sendiri = logout
        $this->getJson('/api/auth/me', $this->bearer($ini))->assertStatus(401);
    }

    public function test_keluarkan_semua_sesi_lain(): void
    {
        $user = $this->makeUser('adopter', 'aktif', 'adopter@contoh.id');
        $orang = $this->makeUser('admin', 'aktif', 'orang@uji.test');
        $ini = $this->tokenFor($user);
        $this->tokenFor($user);
        $this->tokenFor($user);
        $tokenOrang = $this->tokenFor($orang);

        $this->postJson('/api/auth/keluar-semua-lain', [], $this->bearer($ini))->assertOk()->assertJsonPath('data.jumlah', 2);

        $this->getJson('/api/auth/me', $this->bearer($ini))->assertOk();
        $this->assertSame(1, $user->tokens()->count());
        $this->getJson('/api/auth/me', $this->bearer($tokenOrang))->assertOk();                          // akun lain tak terpengaruh
        $this->assertSame(1, AuditLog::where('aktivitas', 'CABUT_SEMUA_SESI')->count());
    }

    public function test_perpanjang_mempertahankan_nama_perangkat(): void
    {
        $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $hp = $this->postJson('/api/auth/login', ['email' => 'staf@uji.test', 'password' => self::PASSWORD, 'perangkat' => 'HP Lapangan'])->assertOk()->json('data.token');

        $baru = $this->postJson('/api/auth/perpanjang', [], $this->bearer($hp))->assertOk()->json('data.token');

        $this->assertSame('HP Lapangan', PersonalAccessToken::findToken($baru)->name);
    }

    public function test_semua_endpoint_akun_sendiri_butuh_login(): void
    {
        $this->app['auth']->forgetGuards();
        foreach ([['putJson', '/api/auth/profil'], ['getJson', '/api/auth/sesi'], ['deleteJson', '/api/auth/sesi/1'], ['postJson', '/api/auth/keluar-semua-lain']] as [$m, $u]) {
            $this->$m($u)->assertStatus(401);
        }
    }

    public function test_notifikasi_lupa_password_tidak_menghitung_di_dashboard_admin(): void
    {
        $this->makeUser('admin', 'aktif', 'staf@uji.test');
        $this->postJson('/api/auth/lupa-password', ['email' => 'staf@uji.test'])->assertOk();
        Notifikasi::where('jenis_notifikasi', 'Lupa Password')->update(['status_pengiriman' => 'GAGAL']);

        $peringatan = collect($this->getJson('/api/admin/dashboard/stats', $this->asRole('admin'))->json('data.peringatan'))->pluck('kode');

        $this->assertNotContains('email_gagal', $peringatan);                                               // bukan urusan admin biasa
        $this->assertSame(1, User::where('email', 'staf@uji.test')->count());
    }
}

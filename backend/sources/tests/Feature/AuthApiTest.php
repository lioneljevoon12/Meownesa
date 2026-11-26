<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

/**
 * dev-backend-02: autentikasi, RBAC, dan audit log.
 * Tes memakai SQLite in-memory (lihat phpunit.xml) — database MySQL dev tidak tersentuh.
 */
class AuthApiTest extends TestCase
{
    use InteractsWithApiAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route khusus tes untuk memeriksa middleware role & audit tanpa menunggu modul lain.
        Route::middleware(['api', 'auth:sanctum', 'role:admin'])->get('/api/_t/admin', fn () => ['ok' => true]);
        Route::middleware(['api', 'auth:sanctum', 'role:superadmin'])->get('/api/_t/super', fn () => ['ok' => true]);
        Route::middleware(['api', 'auth:sanctum', 'role:admin,superadmin'])->get('/api/_t/both', fn () => ['ok' => true]);
        Route::middleware(['api', 'auth:sanctum', 'role:admin', 'audit:kucing'])->group(function () {
            Route::post('/api/_t/kucing', fn () => response()->json(['success' => true, 'data' => ['id_kucing' => 42]], 201));
            Route::put('/api/_t/kucing/{id}', fn () => ['success' => true]);
            Route::delete('/api/_t/kucing/{id}', fn () => ['success' => true]);
            Route::post('/api/_t/gagal', fn () => response()->json(['success' => false], 422));
        });
    }

    // ---------------------------------------------------------------- login

    public function test_login_berhasil_mengembalikan_token_dan_profil(): void
    {
        $this->makeUser('admin');

        $this->postJson('/api/auth/login', ['email' => 'admin@uji.test', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonMissingPath('data.user.password_hash')
            ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['akses']]]);
    }

    public function test_login_gagal_password_salah_dan_email_tak_dikenal_pesan_sama(): void
    {
        $this->makeUser('admin');

        $salah = $this->postJson('/api/auth/login', ['email' => 'admin@uji.test', 'password' => 'salah'])->assertStatus(401);
        $takAda = $this->postJson('/api/auth/login', ['email' => 'siapa@uji.test', 'password' => 'salah'])->assertStatus(401);

        $this->assertSame($salah->json('message'), $takAda->json('message'));
    }

    public function test_login_validasi_input(): void
    {
        $this->postJson('/api/auth/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/auth/login', ['email' => 'bukan-email', 'password' => 'x'])->assertStatus(422);
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $this->makeUser('admin', 'nonaktif');

        $this->postJson('/api/auth/login', ['email' => 'admin@uji.test', 'password' => self::PASSWORD])
            ->assertStatus(403);
    }

    public function test_login_dibatasi_rate_limit(): void
    {
        $this->makeUser('admin');

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/auth/login', ['email' => 'admin@uji.test', 'password' => 'salah'])->assertStatus(401);
        }

        // 429 harus berbentuk JSON seragam berbahasa Indonesia (bukan pesan bawaan Laravel "Too Many Attempts.")
        $this->postJson('/api/auth/login', ['email' => 'admin@uji.test', 'password' => 'salah'])
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.');
    }

    public function test_password_disimpan_sebagai_hash_bukan_teks_biasa(): void
    {
        $user = $this->makeUser('admin');
        $raw = $user->fresh()->getAttributes()['password_hash'];

        $this->assertNotSame(self::PASSWORD, $raw);
        $this->assertTrue(Hash::check(self::PASSWORD, $raw));
        $this->assertStringStartsWith('$2y$', $raw);
    }

    // ------------------------------------------------------------- me/logout

    public function test_me_tanpa_token_401_json(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401)->assertJsonPath('success', false);
        // tanpa header Accept: JSON pun harus 401 JSON, bukan redirect/500
        $this->get('/api/auth/me')->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_me_mengembalikan_profil_dan_hak_akses(): void
    {
        $token = $this->tokenFor($this->makeUser('superadmin'));

        $this->getJson('/api/auth/me', $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('data.role', 'superadmin')
            ->assertJsonPath('data.akses.0', 'akun.kelola');
    }

    public function test_logout_mencabut_token(): void
    {
        $token = $this->tokenFor($this->makeUser('admin'));

        $this->postJson('/api/auth/logout', [], $this->bearer($token))->assertOk();

        $this->app['auth']->forgetGuards(); // paksa guard membaca ulang token dari DB
        $this->getJson('/api/auth/me', $this->bearer($token))->assertStatus(401);
    }

    // ------------------------------------------------------------------ RBAC

    public function test_rbac_role_eksak(): void
    {
        $admin = $this->tokenFor($this->makeUser('admin'));
        $super = $this->tokenFor($this->makeUser('superadmin'));
        $adopter = $this->tokenFor($this->makeUser('adopter'));

        $this->getJson('/api/_t/admin')->assertStatus(401);                              // tanpa login
        $this->getJson('/api/_t/admin', $this->bearer($admin))->assertOk();
        $this->getJson('/api/_t/admin', $this->bearer($super))->assertStatus(403);       // super admin ≠ admin
        $this->getJson('/api/_t/admin', $this->bearer($adopter))->assertStatus(403);
        $this->getJson('/api/_t/super', $this->bearer($admin))->assertStatus(403);
        $this->getJson('/api/_t/super', $this->bearer($super))->assertOk();
        $this->getJson('/api/_t/both', $this->bearer($admin))->assertOk();
        $this->getJson('/api/_t/both', $this->bearer($super))->assertOk();
        $this->getJson('/api/_t/both', $this->bearer($adopter))->assertStatus(403);
    }

    public function test_akun_yang_dinonaktifkan_kehilangan_akses_walau_token_masih_aktif(): void
    {
        $user = $this->makeUser('admin');
        $token = $this->tokenFor($user);

        $user->update(['status_akun' => 'nonaktif']);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/_t/admin', $this->bearer($token))->assertStatus(403);
        $this->getJson('/api/auth/me', $this->bearer($token))->assertStatus(403);
    }

    // ------------------------------------------------------------- audit log

    public function test_login_dan_logout_tercatat_di_audit_log(): void
    {
        $user = $this->makeUser('admin');
        $token = $this->tokenFor($user);
        $this->postJson('/api/auth/logout', [], $this->bearer($token))->assertOk();

        $this->assertDatabaseHas('audit_log', ['id_user' => $user->id_user, 'aktivitas' => 'LOGIN']);
        $this->assertDatabaseHas('audit_log', ['id_user' => $user->id_user, 'aktivitas' => 'LOGOUT']);
    }

    public function test_login_gagal_tercatat(): void
    {
        $user = $this->makeUser('admin');
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'salah']);

        $this->assertDatabaseHas('audit_log', ['id_user' => $user->id_user, 'aktivitas' => 'LOGIN_GAGAL']);
    }

    public function test_mutasi_data_otomatis_tercatat_lengkap(): void
    {
        $user = $this->makeUser('admin');
        $h = $this->bearer($this->tokenFor($user));

        $this->postJson('/api/_t/kucing', [], $h)->assertCreated();   // id dari respons (data.id_kucing)
        $this->putJson('/api/_t/kucing/7', [], $h)->assertOk();        // id dari route
        $this->deleteJson('/api/_t/kucing/7', [], $h)->assertOk();

        $this->assertDatabaseHas('audit_log', ['id_user' => $user->id_user, 'aktivitas' => 'CREATE', 'nama_tabel' => 'kucing', 'id_data' => 42]);
        $this->assertDatabaseHas('audit_log', ['id_user' => $user->id_user, 'aktivitas' => 'UPDATE', 'nama_tabel' => 'kucing', 'id_data' => 7]);
        $this->assertDatabaseHas('audit_log', ['id_user' => $user->id_user, 'aktivitas' => 'DELETE', 'nama_tabel' => 'kucing', 'id_data' => 7]);
        $this->assertNotNull(AuditLog::where('aktivitas', 'CREATE')->first()->waktu_aktivitas);
    }

    public function test_aksi_gagal_dan_baca_data_tidak_tercatat(): void
    {
        $h = $this->bearer($this->tokenFor($this->makeUser('admin')));
        $before = AuditLog::where('nama_tabel', 'kucing')->count();

        $this->postJson('/api/_t/gagal', [], $h)->assertStatus(422);   // respons error
        $this->getJson('/api/_t/admin', $h)->assertOk();               // GET

        $this->assertSame($before, AuditLog::where('nama_tabel', 'kucing')->count());
    }

    public function test_endpoint_tidak_dikenal_404_json(): void
    {
        $this->getJson('/api/tidak-ada')->assertStatus(404)->assertJsonPath('success', false);
    }
}

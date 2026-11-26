<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\PengajuanAdopsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/** dev-backend-09: aktivasi akun adopter lewat tautan sekali pakai (+ kirim ulang tautan). */
class AktivasiAkunApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, MembuatPengajuan, RefreshDatabase;

    private const PASSWORD_BARU = 'KucingLucu123';

    private $kucing;
    private PengajuanAdopsi $pengajuan;
    private array $q;   // ['email' => ..., 'token' => ...] dari tautan di notifikasi

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
        Storage::fake('serah_terima');
        $this->kucing = $this->buatKucing(['status_kucing' => 'Proses Screening']);
        $this->pengajuan = $this->buatPengajuan($this->kucing, 'DISETUJUI', ['email' => 'dewi@contoh.id', 'nama_pemohon' => 'Dewi Anggraini']);
        $this->buatScreening($this->pengajuan, 'Lolos');

        $this->post("/api/admin/adopsi/{$this->pengajuan->id_pengajuan}/serah-terima", ['tanggal_serah_terima' => now('Asia/Jakarta')->toDateString()],
            $this->asRole('admin') + ['Accept' => 'application/json'])->assertCreated();
        $this->q = $this->tautan();
        $this->app['auth']->forgetGuards();
    }

    private function tautan(): array
    {
        $isi = Notifikasi::where('id_pengajuan', $this->pengajuan->id_pengajuan)->whereIn('jenis_notifikasi', ['Serah Terima', 'Aktivasi Akun'])
            ->orderByDesc('id_notifikasi')->value('isi_pesan');
        preg_match('#aktivasi-akun\?([^\s)]+)#', $isi, $m);
        parse_str($m[1], $q);

        return $q;
    }

    private function aktifkan(array $o = [])
    {
        return $this->postJson('/api/auth/aktivasi', $o + $this->q + ['password' => self::PASSWORD_BARU, 'password_confirmation' => self::PASSWORD_BARU]);
    }

    public function test_adopter_mengaktifkan_akun_lalu_bisa_login_sebagai_adopter(): void
    {
        $this->aktifkan()->assertOk()->assertJsonPath('success', true);

        $user = User::where('email', 'dewi@contoh.id')->first();
        $this->assertSame('aktif', $user->status_akun);
        $this->assertTrue(Hash::check(self::PASSWORD_BARU, $user->getAttributes()['password_hash']));
        $this->assertStringStartsWith('$2y$', $user->getAttributes()['password_hash']);   // bcrypt (cost 12 di produksi; 4 di lingkungan tes)
        $this->assertDatabaseHas('audit_log', ['id_user' => $user->id_user, 'aktivitas' => 'AKTIVASI_AKUN', 'nama_tabel' => 'users']);

        $this->postJson('/api/auth/login', ['email' => 'dewi@contoh.id', 'password' => self::PASSWORD_BARU])->assertOk()
            ->assertJsonPath('data.user.role', 'adopter')
            ->assertJsonPath('data.user.nama', 'Dewi Anggraini')
            ->assertJsonPath('data.user.akses.0', 'pengingat.terima');
    }

    public function test_adopter_tidak_punya_akses_ke_endpoint_admin_maupun_super_admin(): void
    {
        $this->aktifkan()->assertOk();
        $token = $this->postJson('/api/auth/login', ['email' => 'dewi@contoh.id', 'password' => self::PASSWORD_BARU])->json('data.token');
        $h = $this->bearer($token);

        $this->getJson('/api/admin/adopsi', $h)->assertStatus(403);
        $this->getJson('/api/admin/feeding', $h)->assertStatus(403);
        $this->getJson("/api/admin/serah-terima/1", $h)->assertStatus(403);
        $this->getJson('/api/auth/me', $h)->assertOk()->assertJsonPath('data.role', 'adopter');
    }

    public function test_tautan_hanya_bisa_dipakai_sekali(): void
    {
        $this->aktifkan()->assertOk();

        $this->aktifkan(['password' => 'PasswordLain456', 'password_confirmation' => 'PasswordLain456'])->assertStatus(422)->assertJsonValidationErrors('token');
        $this->assertTrue(Hash::check(self::PASSWORD_BARU, User::where('email', 'dewi@contoh.id')->first()->getAttributes()['password_hash']));   // tidak tertimpa
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'dewi@contoh.id']);
    }

    public function test_token_salah_email_tak_dikenal_dan_akun_internal_menghasilkan_pesan_yang_sama(): void
    {
        $this->makeUser('admin', 'aktif', 'staf@jagasatwa.test');

        $a = $this->aktifkan(['token' => 'token-ngawur'])->assertStatus(422);
        $b = $this->aktifkan(['email' => 'orang@tidakada.id'])->assertStatus(422);
        $c = $this->aktifkan(['email' => 'staf@jagasatwa.test'])->assertStatus(422);

        $this->assertSame($a->json('errors.token'), $b->json('errors.token'));
        $this->assertSame($a->json('errors.token'), $c->json('errors.token'));
        $this->assertSame('menunggu_aktivasi', User::where('email', 'dewi@contoh.id')->value('status_akun'));
        $this->assertSame('aktif', User::where('email', 'staf@jagasatwa.test')->value('status_akun'));   // akun internal tidak terpengaruh
    }

    public function test_tautan_kedaluwarsa_setelah_7_hari(): void
    {
        $this->travel(6)->days();
        $this->aktifkan(['password' => 'KucingLucu123', 'password_confirmation' => 'KucingLucu123'])->assertOk();   // hari ke-6 masih berlaku
    }

    public function test_tautan_kedaluwarsa_setelah_lebih_dari_7_hari_ditolak(): void
    {
        $this->travel(8)->days();

        $this->aktifkan()->assertStatus(422)->assertJsonValidationErrors('token');
        $this->assertSame('menunggu_aktivasi', User::where('email', 'dewi@contoh.id')->value('status_akun'));
    }

    public function test_akun_yang_dinonaktifkan_tidak_bisa_hidup_lagi_lewat_tautan_lama(): void
    {
        User::where('email', 'dewi@contoh.id')->update(['status_akun' => 'nonaktif']);

        $this->aktifkan()->assertStatus(422);
        $this->assertSame('nonaktif', User::where('email', 'dewi@contoh.id')->value('status_akun'));
    }

    public function test_aturan_password(): void
    {
        foreach ([
            ['password' => 'pendek1', 'password_confirmation' => 'pendek1'],                 // < 8
            ['password' => 'hurufsajahurufsaja', 'password_confirmation' => 'hurufsajahurufsaja'],   // tanpa angka
            ['password' => '1234567890123', 'password_confirmation' => '1234567890123'],     // tanpa huruf
            ['password' => self::PASSWORD_BARU, 'password_confirmation' => 'BedaSemua123'],  // konfirmasi beda
            ['password' => str_repeat('a1', 33), 'password_confirmation' => str_repeat('a1', 33)],   // > 64
            ['password' => '', 'password_confirmation' => ''],
        ] as $salah) {
            Cache::flush();   // batas 5 percobaan/menit dinonaktifkan di sini; perilakunya diuji tersendiri
            $this->aktifkan($salah)->assertStatus(422)->assertJsonValidationErrors('password');
        }
        Cache::flush();
        $this->aktifkan(['email' => 'x', 'password' => 'a', 'token' => ''])->assertStatus(422);
        Cache::flush();

        // setelah semua percobaan gagal, tautan masih utuh dan akun belum aktif
        $this->assertSame('menunggu_aktivasi', User::where('email', 'dewi@contoh.id')->value('status_akun'));
        $this->aktifkan()->assertOk();
    }

    public function test_aktivasi_dibatasi_5_percobaan_per_menit(): void
    {
        foreach (range(1, 5) as $i) {
            $this->aktifkan(['token' => 'salah-'.$i])->assertStatus(422);
        }

        $this->aktifkan()->assertStatus(429)->assertJsonPath('success', false);   // bahkan tautan yang benar ikut ditahan
    }

    // ------------------------------------------------------ kirim ulang tautan

    public function test_kirim_ulang_membuat_tautan_baru_dan_tautan_lama_tidak_berlaku(): void
    {
        $tokenLama = $this->q['token'];
        $h = $this->asRole('admin');

        $this->postJson("/api/admin/adopsi/{$this->pengajuan->id_pengajuan}/kirim-ulang-aktivasi", [], $h)->assertOk()
            ->assertJsonPath('data.id_pengajuan', $this->pengajuan->id_pengajuan);

        $baru = $this->tautan();
        $this->assertNotSame($tokenLama, $baru['token']);
        $this->assertSame(2, Notifikasi::where('id_pengajuan', $this->pengajuan->id_pengajuan)->where('jenis_notifikasi', 'Aktivasi Akun')->count());   // WhatsApp + Email
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'KIRIM_ULANG_AKTIVASI', 'nama_tabel' => 'pengajuan_adopsi', 'id_data' => $this->pengajuan->id_pengajuan]);

        $this->app['auth']->forgetGuards();
        $this->aktifkan(['token' => $tokenLama])->assertStatus(422);   // tautan lama mati
        $this->aktifkan($baru)->assertOk();                             // tautan baru bekerja
    }

    public function test_kirim_ulang_ditolak_bila_akun_sudah_aktif_atau_belum_serah_terima(): void
    {
        $h = $this->asRole('admin');
        $this->aktifkan()->assertOk();
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/admin/adopsi/{$this->pengajuan->id_pengajuan}/kirim-ulang-aktivasi", [], $h)->assertStatus(422)->assertJsonValidationErrors('status');

        $belum = $this->buatPengajuan($this->buatKucing(['status_kucing' => 'Proses Screening']), 'DISETUJUI');
        $this->postJson("/api/admin/adopsi/{$belum->id_pengajuan}/kirim-ulang-aktivasi", [], $h)->assertStatus(422);
        $this->postJson('/api/admin/adopsi/99999/kirim-ulang-aktivasi', [], $h)->assertStatus(404);
        $this->assertSame(0, Notifikasi::where('jenis_notifikasi', 'Aktivasi Akun')->count());
    }

    public function test_token_tidak_pernah_tersimpan_polos_di_basis_data_kecuali_di_pesan_notifikasi(): void
    {
        $tabelToken = DB::table('password_reset_tokens')->where('email', 'dewi@contoh.id')->first();

        $this->assertNotSame($this->q['token'], $tabelToken->token);
        $this->assertTrue(Hash::check($this->q['token'], $tabelToken->token));
        $this->assertSame(0, AuditLog::where('aktivitas', 'like', '%TOKEN%')->count());
    }
}

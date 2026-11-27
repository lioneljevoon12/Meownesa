<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CatatanPengajuan;
use App\Models\Kucing;
use App\Models\LampiranPengajuan;
use App\Models\Notifikasi;
use App\Models\PengajuanAdopsi;
use App\Models\SerahTerima;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/**
 * dev-backend-17: pelamar menarik pengajuan dan mengganti lampiran (kode tracking + nomor pengajuan), catatan internal
 * admin per pengajuan (termasuk kunjungan rumah), dan lembar BAST yang dapat dicetak. Waktu dibekukan 26 Nov 2026 10.00 WIB.
 */
class PengajuanPelamarDanCatatanApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, MembuatPengajuan, RefreshDatabase;

    /** @var array<string, string> */
    private array $token = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));
        $this->siapkanWilayah();
        Storage::fake('lampiran');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(string $email = 'petugas@uji.test'): array
    {
        $this->token[$email] ??= $this->tokenFor($this->makeUser('admin', 'aktif', $email));

        return $this->bearer($this->token[$email]);
    }

    private function pengajuan(string $status = 'MENUNGGU', string $statusKucing = 'Tersedia'): PengajuanAdopsi
    {
        return $this->buatPengajuan($this->buatKucing(['status_kucing' => $statusKucing]), $status, ['tanggal_pengajuan' => '2026-11-20']);
    }

    private function tarik(PengajuanAdopsi $p, array $o = [], ?string $kode = null)
    {
        return $this->postJson('/api/adopsi/tracking/'.($kode ?? $p->kode_tracking).'/tarik', $o + ['nomor_pengajuan' => $p->nomor_pengajuan]);
    }

    // ------------------------------------------------------------ tarik pengajuan

    public function test_pelamar_menarik_pengajuan_yang_belum_diperiksa(): void
    {
        $p = $this->pengajuan('MENUNGGU');

        $this->tarik($p)->assertOk()->assertJsonPath('data.status', 'DIBATALKAN')->assertJsonPath('data.status_label', 'Dibatalkan');

        $this->assertSame('DIBATALKAN', $p->refresh()->status_pengajuan);
        $this->assertSame('Tersedia', $p->kucing->refresh()->status_kucing);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'TARIK_PENGAJUAN', 'nama_tabel' => 'pengajuan_adopsi', 'id_data' => $p->id_pengajuan, 'id_user' => null]);
        $this->assertSame(2, Notifikasi::where('id_pengajuan', $p->id_pengajuan)->count());          // konfirmasi ke pelamar (WhatsApp + email)
        $this->getJson("/api/adopsi/tracking/{$p->kode_tracking}")->assertOk()->assertJsonPath('data.status', 'DIBATALKAN')
            ->assertJsonPath('data.tahap.1.selesai', false);
    }

    public function test_menarik_pengajuan_yang_memegang_kucing_mengembalikannya_ke_tersedia(): void
    {
        foreach (['SCREENING', 'DISETUJUI'] as $status) {
            $p = $this->pengajuan($status, 'Proses Screening');

            $this->tarik($p)->assertOk();

            $this->assertSame('Tersedia', $p->kucing->refresh()->status_kucing, "dari {$status}");
            $this->assertDatabaseHas('audit_log', ['aktivitas' => 'SINKRON_STATUS_KUCING', 'id_data' => $p->id_kucing]);
        }
    }

    public function test_kucing_yang_dilepas_bisa_diajukan_lagi_oleh_pelamar_lain(): void
    {
        $p = $this->pengajuan('SCREENING', 'Proses Screening');
        $this->tarik($p)->assertOk();

        $this->post('/api/adopsi/ajukan', [
            'id_kucing' => $p->id_kucing, 'nama_pemohon' => 'Pelamar Baru', 'nomor_whatsapp' => '081234567891', 'email' => 'baru@contoh.id',
            'alamat' => 'Jl. Contoh No. 5, Surabaya', 'persetujuan_data' => true,
        ], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_menarik_butuh_kode_dan_nomor_yang_cocok_tanpa_membedakan_penyebab_gagal(): void
    {
        $p = $this->pengajuan();
        $lain = $this->pengajuan();

        $salahNomor = $this->tarik($p, ['nomor_pengajuan' => $lain->nomor_pengajuan]);
        $salahKode = $this->tarik($p, [], 'MN-TIDAK-ADA-0000');
        $nomorKecil = $this->tarik($p, ['nomor_pengajuan' => strtolower($p->nomor_pengajuan)]);       // huruf kecil boleh

        $salahNomor->assertNotFound();
        $salahKode->assertNotFound();
        $this->assertSame($salahNomor->getContent(), $salahKode->getContent());                          // pesan persis sama
        $nomorKecil->assertOk();
        $this->assertSame('MENUNGGU', $lain->refresh()->status_pengajuan);

        $this->postJson("/api/adopsi/tracking/{$lain->kode_tracking}/tarik", [])->assertStatus(422)->assertJsonValidationErrors('nomor_pengajuan');
    }

    public function test_pengajuan_yang_sudah_diputuskan_atau_ditarik_tidak_bisa_ditarik(): void
    {
        foreach (['DITOLAK', 'SELESAI', 'DIBATALKAN'] as $status) {
            $p = $this->pengajuan($status);
            $this->tarik($p)->assertStatus(422)->assertJsonValidationErrors('status');
            $this->assertSame($status, $p->refresh()->status_pengajuan);
        }
    }

    public function test_admin_tidak_bisa_mengubah_pengajuan_yang_ditarik_dan_melihatnya_di_daftar(): void
    {
        $p = $this->pengajuan();
        $this->tarik($p)->assertOk();
        $h = $this->admin();

        $this->putJson("/api/admin/adopsi/{$p->id_pengajuan}/status", ['status' => 'SCREENING'], $h)->assertStatus(422);
        $this->getJson('/api/admin/adopsi?status=DIBATALKAN', $h)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.jumlah_per_status.DIBATALKAN', 1);
        $antre = collect($this->getJson('/api/admin/dashboard/antrean-terbaru', $h)->json('data'))->pluck('id_pengajuan');
        $this->assertNotContains($p->id_pengajuan, $antre);                                               // tidak lagi butuh tindakan
    }

    public function test_menarik_dibatasi_laju_per_ip(): void
    {
        $p = $this->pengajuan();
        foreach (range(1, 30) as $i) {
            $this->tarik($p, ['nomor_pengajuan' => 'SALAH'])->assertNotFound();
        }

        $this->tarik($p)->assertStatus(429);                                                              // 30/jam per IP, sama dengan pengajuan baru
    }

    // ------------------------------------------------------------ lampiran

    private function unggah(PengajuanAdopsi $p, string $jenis = 'KTP')
    {
        return $this->post('/api/adopsi/upload-lampiran', [
            'kode_tracking' => $p->kode_tracking, 'jenis_lampiran' => $jenis, 'berkas' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
    }

    private function hapus(PengajuanAdopsi $p, int $idLampiran, array $o = [])
    {
        return $this->deleteJson("/api/adopsi/tracking/{$p->kode_tracking}/lampiran/{$idLampiran}", $o + ['nomor_pengajuan' => $p->nomor_pengajuan]);
    }

    public function test_pelamar_menghapus_lampiran_yang_salah_lalu_mengunggah_ulang(): void
    {
        $p = $this->pengajuan();
        $id = $this->unggah($p)->assertCreated()->json('data.id_lampiran');
        $path = LampiranPengajuan::findOrFail($id)->url_file;
        Storage::disk('lampiran')->assertExists($path);

        $this->hapus($p, $id)->assertOk()->assertJsonPath('data.jumlah_lampiran', 0);

        Storage::disk('lampiran')->assertMissing($path);                                                  // berkas fisik ikut terhapus
        $this->assertSame(0, LampiranPengajuan::count());
        $this->unggah($p)->assertCreated();                                                               // bisa diunggah ulang
    }

    public function test_menghapus_lampiran_butuh_nomor_benar_dan_hanya_milik_pengajuan_sendiri(): void
    {
        $p = $this->pengajuan();
        $lain = $this->pengajuan();
        $milikSaya = $this->unggah($p)->assertCreated()->json('data.id_lampiran');
        $milikLain = $this->unggah($lain)->assertCreated()->json('data.id_lampiran');

        $this->hapus($p, $milikSaya, ['nomor_pengajuan' => 'ADP-9999-999'])->assertNotFound();
        $this->hapus($p, $milikLain)->assertNotFound();                                                    // lampiran orang lain tidak bisa dihapus
        $this->hapus($p, 99999)->assertNotFound();
        $this->deleteJson("/api/adopsi/tracking/{$p->kode_tracking}/lampiran/{$milikSaya}", [])->assertStatus(422)->assertJsonValidationErrors('nomor_pengajuan');

        $this->assertSame(2, LampiranPengajuan::count());
    }

    public function test_lampiran_pengajuan_yang_sudah_diputuskan_tidak_bisa_dihapus(): void
    {
        $p = $this->pengajuan('SCREENING', 'Proses Screening');
        $id = $this->unggah($p)->assertCreated()->json('data.id_lampiran');
        $p->update(['status_pengajuan' => 'DISETUJUI']);

        $this->hapus($p, $id)->assertStatus(422)->assertJsonValidationErrors('kode_tracking');

        $this->assertSame(1, LampiranPengajuan::count());
    }

    // ------------------------------------------------------------ catatan internal

    public function test_admin_menambah_catatan_dan_kunjungan_rumah_yang_tidak_terlihat_publik(): void
    {
        $p = $this->pengajuan('SCREENING', 'Proses Screening');
        $h = $this->admin();

        $this->postJson("/api/admin/adopsi/{$p->id_pengajuan}/catatan", ['isi' => '  Pelamar ramah, rumah rapi.  '], $h)->assertCreated()
            ->assertJsonPath('data.jenis', 'Catatan')->assertJsonPath('data.isi', 'Pelamar ramah, rumah rapi.')->assertJsonPath('data.tanggal_kunjungan', null)
            ->assertJsonPath('data.penulis.nama', 'Uji admin');
        $kunjungan = $this->postJson("/api/admin/adopsi/{$p->id_pengajuan}/catatan", ['jenis' => 'Kunjungan Rumah', 'isi' => 'Dijadwalkan Sabtu pagi.', 'tanggal_kunjungan' => '2026-11-28'], $h)->assertCreated()
            ->assertJsonPath('data.tanggal_kunjungan', '2026-11-28');

        $daftar = $this->getJson("/api/admin/adopsi/{$p->id_pengajuan}/catatan", $h)->assertOk();
        $this->assertSame([$kunjungan->json('data.id_catatan'), $daftar->json('data.1.id_catatan')], array_column($daftar->json('data'), 'id_catatan'));   // terbaru di atas
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'catatan_pengajuan', 'id_data' => $kunjungan->json('data.id_catatan')]);

        // tidak pernah bocor ke pelacakan publik
        $publik = $this->getJson("/api/adopsi/tracking/{$p->kode_tracking}")->assertOk()->getContent();
        $this->assertStringNotContainsString('rumah rapi', $publik);
        $this->assertStringNotContainsString('Kunjungan', $publik);
    }

    public function test_validasi_catatan_pengajuan(): void
    {
        $p = $this->pengajuan();
        $h = $this->admin();
        $uri = "/api/admin/adopsi/{$p->id_pengajuan}/catatan";

        $this->postJson($uri, [], $h)->assertStatus(422)->assertJsonValidationErrors('isi');
        foreach ([
            ['isi' => 'ab'],
            ['isi' => str_repeat('a', 2001)],
            ['isi' => 'Catatan biasa', 'jenis' => 'Rahasia'],
            ['isi' => 'Catatan biasa', 'tanggal_kunjungan' => '2026-11-28'],                                  // tanggal hanya untuk Kunjungan Rumah
            ['isi' => 'Kunjungan', 'jenis' => 'Kunjungan Rumah', 'tanggal_kunjungan' => '28-11-2026'],
            ['isi' => 'Kunjungan', 'jenis' => 'Kunjungan Rumah', 'tanggal_kunjungan' => '2019-01-01'],
            ['isi' => 'Kunjungan', 'jenis' => 'Kunjungan Rumah', 'tanggal_kunjungan' => '2030-01-01'],           // terlalu jauh
        ] as $salah) {
            $this->postJson($uri, $salah, $h)->assertStatus(422);
        }
        $this->postJson('/api/admin/adopsi/99999/catatan', ['isi' => 'Catatan biasa'], $h)->assertNotFound();
        $this->getJson('/api/admin/adopsi/99999/catatan', $h)->assertNotFound();
        $this->assertSame(0, CatatanPengajuan::count());
    }

    public function test_hanya_penulis_yang_boleh_menghapus_catatannya_dan_hanya_admin_yang_boleh_mengakses(): void
    {
        $p = $this->pengajuan();
        $id = $this->postJson("/api/admin/adopsi/{$p->id_pengajuan}/catatan", ['isi' => 'Catatan saya'], $this->admin('a@uji.test'))->assertCreated()->json('data.id_catatan');

        $this->deleteJson("/api/admin/adopsi/{$p->id_pengajuan}/catatan/{$id}", [], $this->admin('b@uji.test'))->assertStatus(403);
        $this->assertSame(1, CatatanPengajuan::count());
        $lain = $this->pengajuan();
        $this->deleteJson("/api/admin/adopsi/{$lain->id_pengajuan}/catatan/{$id}", [], $this->admin('a@uji.test'))->assertNotFound();   // catatan milik pengajuan lain

        $this->deleteJson("/api/admin/adopsi/{$p->id_pengajuan}/catatan/{$id}", [], $this->admin('a@uji.test'))->assertOk();
        $this->assertSame(0, CatatanPengajuan::count());
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'DELETE', 'nama_tabel' => 'catatan_pengajuan', 'id_data' => $id]);

        foreach (['superadmin', 'adopter'] as $role) {
            $h = $this->asRole($role);
            $this->getJson("/api/admin/adopsi/{$p->id_pengajuan}/catatan", $h)->assertStatus(403);
            $this->postJson("/api/admin/adopsi/{$p->id_pengajuan}/catatan", ['isi' => 'Tidak berwenang'], $h)->assertStatus(403);
        }
    }

    // ------------------------------------------------------------ BAST

    private function serahTerima(array $pengajuan = []): SerahTerima
    {
        $k = $this->buatKucing(['status_kucing' => 'Diadopsi', 'nama_kucing' => 'Mochi', 'deskripsi' => 'Belang tiga, ekor pendek']);
        $p = $this->buatPengajuan($k, 'SELESAI', $pengajuan + ['tanggal_pengajuan' => '2026-11-01', 'nama_pemohon' => 'Siti Aminah', 'alamat' => 'Jl. Merdeka 17, Surabaya']);

        return SerahTerima::create(['id_pengajuan' => $p->id_pengajuan, 'tanggal_serah_terima' => '2026-11-25', 'status_serah_terima' => 'Selesai']);
    }

    public function test_bast_berupa_html_siap_cetak_dengan_data_serah_terima(): void
    {
        $st = $this->serahTerima();

        $res = $this->get("/api/admin/serah-terima/{$st->id_serah_terima}/bast", $this->admin())->assertOk();

        $this->assertStringContainsString('text/html', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $res->headers->get('X-Robots-Tag'));
        foreach (['Berita Acara Serah Terima', $st->pengajuanAdopsi->nomor_pengajuan, 'Rabu, 25 November 2026', 'Mochi', 'Belang tiga, ekor pendek', 'Siti Aminah', 'Jl. Merdeka 17, Surabaya', 'Danau (Kampus Ketintang)', 'Uji admin', 'templat'] as $isi) {
            $this->assertStringContainsString($isi, $res->getContent(), $isi);
        }
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CETAK_BAST', 'nama_tabel' => 'serah_terima', 'id_data' => $st->id_serah_terima]);
    }

    public function test_bast_mengamankan_data_dari_html_berbahaya(): void
    {
        $st = $this->serahTerima(['nama_pemohon' => '<script>alert(1)</script>Jahat', 'alamat' => '"><img src=x onerror=alert(2)>']);

        $isi = $this->get("/api/admin/serah-terima/{$st->id_serah_terima}/bast", $this->admin())->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $isi);
        $this->assertStringNotContainsString('<img src=x', $isi);
        $this->assertStringContainsString('&lt;script&gt;alert(1)', $isi);
    }

    public function test_bast_hanya_untuk_admin_dan_404_bila_tidak_ada(): void
    {
        $st = $this->serahTerima();
        $uri = "/api/admin/serah-terima/{$st->id_serah_terima}/bast";

        $this->app['auth']->forgetGuards();
        $this->getJson($uri)->assertStatus(401);
        $this->getJson($uri, $this->asRole('superadmin'))->assertStatus(403);
        $this->getJson($uri, $this->asRole('adopter'))->assertStatus(403);
        $this->get('/api/admin/serah-terima/99999/bast', $this->admin())->assertNotFound();
        $this->assertSame(0, AuditLog::where('aktivitas', 'CETAK_BAST')->count());
        $this->assertSame(1, Kucing::where('nama_kucing', 'Mochi')->count());
    }
}

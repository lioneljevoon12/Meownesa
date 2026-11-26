<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LampiranPengajuan;
use App\Models\PengajuanAdopsi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/** dev-backend-08: antrean, detail, dan unduh lampiran untuk admin (MEOW-F-16, BR-05, BR-08, NF-04). */
class PengajuanAdminApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, MembuatPengajuan, RefreshDatabase;

    private $kucing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
        Storage::fake('lampiran');
        $this->kucing = $this->buatKucing(['status_kucing' => 'Tersedia', 'status_steril' => 'Sudah']);
    }

    private function ajukanPublik(array $o = [], array $berkas = [])
    {
        return $this->post('/api/adopsi/ajukan', $o + $berkas + [
            'id_kucing' => $this->kucing->id_kucing, 'nama_pemohon' => 'Dewi Anggraini', 'nomor_whatsapp' => '081234567890',
            'email' => 'dewi@contoh.id', 'alamat' => 'Jl. Ketintang Baru No. 12, Surabaya', 'persetujuan_data' => true,
        ], ['Accept' => 'application/json']);
    }

    // ------------------------------------------------------------ hak akses

    public function test_semua_endpoint_adopsi_admin_butuh_role_admin_super_admin_ditolak(): void
    {
        $p = $this->buatPengajuan();
        $rute = [
            ['getJson', '/api/admin/adopsi'],
            ['getJson', "/api/admin/adopsi/{$p->id_pengajuan}"],
            ['getJson', "/api/admin/adopsi/{$p->id_pengajuan}/lampiran/1"],
            ['putJson', "/api/admin/adopsi/{$p->id_pengajuan}/status"],
            ['postJson', "/api/admin/adopsi/{$p->id_pengajuan}/screening"],
        ];
        $panggil = fn (string $m, string $u, array $h = []) => $m === 'getJson' ? $this->getJson($u, $h) : $this->$m($u, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);   // BR-05: keputusan bukan wewenang Super Admin
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);
        }
        $this->assertSame('MENUNGGU', $p->fresh()->status_pengajuan);
        $this->assertDatabaseCount('screening_adopsi', 0);
    }

    // ---------------------------------------------------------------- daftar

    public function test_antrean_diurutkan_dari_yang_paling_lama_dan_memuat_kontak_untuk_admin(): void
    {
        $baru = $this->buatPengajuan(null, 'MENUNGGU', ['tanggal_pengajuan' => '2026-11-20']);
        $lama = $this->buatPengajuan(null, 'MENUNGGU', ['tanggal_pengajuan' => '2026-11-02']);
        $tengah = $this->buatPengajuan(null, 'MENUNGGU', ['tanggal_pengajuan' => '2026-11-10']);
        $h = $this->asRole('admin');

        $res = $this->getJson('/api/admin/adopsi', $h)->assertOk();
        $this->assertSame([$lama->id_pengajuan, $tengah->id_pengajuan, $baru->id_pengajuan], collect($res->json('data'))->pluck('id_pengajuan')->all());
        $this->assertSame([$baru->id_pengajuan, $tengah->id_pengajuan, $lama->id_pengajuan],
            collect($this->getJson('/api/admin/adopsi?sort=terbaru', $h)->json('data'))->pluck('id_pengajuan')->all());

        $item = $res->json('data.0');
        $this->assertEqualsCanonicalizing(
            ['id_pengajuan', 'nomor_pengajuan', 'status_pengajuan', 'status_label', 'tanggal_pengajuan', 'nama_pemohon', 'nomor_whatsapp', 'email', 'kucing', 'jumlah_lampiran', 'hasil_screening'],
            array_keys($item)
        );
        $this->assertSame($lama->email, $item['email']);   // admin berwenang melihat kontak
        $this->assertSame(0, $item['jumlah_lampiran']);
        $this->assertNull($item['hasil_screening']);
        $this->assertSame($this->kucing->nama_kucing, $item['kucing']['nama_kucing']);
    }

    public function test_filter_pencarian_dan_jumlah_per_status(): void
    {
        $lain = $this->buatKucing(['status_kucing' => 'Tersedia']);
        $a = $this->buatPengajuan(null, 'MENUNGGU', ['nama_pemohon' => 'Budi Santoso', 'tanggal_pengajuan' => '2026-11-03']);
        $b = $this->buatPengajuan($lain, 'SCREENING', ['nama_pemohon' => 'Citra Lestari', 'email' => 'citra@khusus.id', 'tanggal_pengajuan' => '2026-11-08']);
        $c = $this->buatPengajuan(null, 'DITOLAK', ['tanggal_pengajuan' => '2026-11-15']);
        $h = $this->asRole('admin');
        $ids = fn (string $q) => collect($this->getJson("/api/admin/adopsi?{$q}", $h)->assertOk()->json('data'))->pluck('id_pengajuan')->sort()->values()->all();

        $this->assertSame([$b->id_pengajuan], $ids('status=SCREENING'));
        $this->assertSame([$b->id_pengajuan], $ids('id_kucing='.$lain->id_kucing));
        $this->assertSame([$a->id_pengajuan], $ids('q=budi'));
        $this->assertSame([$b->id_pengajuan], $ids('q=khusus.id'));
        $this->assertSame([$a->id_pengajuan], $ids('q='.$a->nomor_pengajuan));
        $this->assertSame([$c->id_pengajuan], $ids('q='.$c->kode_tracking));
        $this->assertSame([$a->id_pengajuan, $b->id_pengajuan], $ids('dari=2026-11-01&sampai=2026-11-10'));
        $this->assertSame([], $ids('q=tidak-ada-sama-sekali'));

        // jumlah per status tidak terpengaruh filter
        $meta = $this->getJson('/api/admin/adopsi?status=DITOLAK', $h)->json('meta');
        $this->assertSame(['MENUNGGU' => 1, 'SCREENING' => 1, 'DISETUJUI' => 0, 'DITOLAK' => 1, 'SELESAI' => 0], $meta['jumlah_per_status']);
        $this->assertSame(1, $meta['total']);
    }

    public function test_paginasi_dan_filter_tidak_valid(): void
    {
        foreach (range(1, 5) as $i) {
            $this->buatPengajuan();
        }
        $h = $this->asRole('admin');

        $this->getJson('/api/admin/adopsi?per_page=2&page=3', $h)->assertOk()
            ->assertJsonPath('meta.halaman', 3)->assertJsonPath('meta.halaman_akhir', 3)->assertJsonCount(1, 'data');

        foreach (['status=APA', 'id_kucing=999', 'dari=kemarin', 'dari=2026-11-10&sampai=2026-11-01', 'per_page=101', 'page=0', 'sort=acak'] as $q) {
            $this->getJson("/api/admin/adopsi?{$q}", $h)->assertStatus(422);
        }
    }

    // ---------------------------------------------------------------- detail

    public function test_detail_memuat_alamat_kode_tracking_lampiran_screening_dan_notifikasi(): void
    {
        $this->ajukanPublik([], ['ktp' => UploadedFile::fake()->image('ktp.jpg', 800, 500)])->assertCreated();
        $p = PengajuanAdopsi::first();
        $kode = $p->kode_tracking;

        $res = $this->getJson("/api/admin/adopsi/{$p->id_pengajuan}", $this->asRole('admin'))->assertOk()
            ->assertJsonPath('data.alamat', 'Jl. Ketintang Baru No. 12, Surabaya')
            ->assertJsonPath('data.kode_tracking', $kode)
            ->assertJsonPath('data.nomor_whatsapp', '6281234567890')
            ->assertJsonPath('data.screening', null)
            ->assertJsonCount(1, 'data.lampiran')
            ->assertJsonCount(2, 'data.notifikasi');

        $lampiran = $res->json('data.lampiran.0');
        $this->assertSame('KTP', $lampiran['jenis_lampiran']);
        $this->assertStringEndsWith("/api/admin/adopsi/{$p->id_pengajuan}/lampiran/{$lampiran['id_lampiran']}", $lampiran['unduh']);
        $this->assertStringNotContainsString(LampiranPengajuan::first()->url_file, json_encode($res->json()));   // jalur berkas tak dibocorkan
    }

    public function test_detail_tidak_ada_404(): void
    {
        $this->getJson('/api/admin/adopsi/99999', $this->asRole('admin'))->assertStatus(404);
    }

    // ------------------------------------------------------- unduh lampiran

    public function test_admin_mengunduh_lampiran_dan_akses_tercatat_di_audit_log(): void
    {
        $this->ajukanPublik([], [
            'ktp'               => UploadedFile::fake()->image('ktp.png', 800, 500),
            'dokumen_pendukung' => UploadedFile::fake()->create('surat.pdf', 20, 'application/pdf'),
        ])->assertCreated();
        $p = PengajuanAdopsi::first();
        $ktp = LampiranPengajuan::where('jenis_lampiran', 'KTP')->first();
        $pdf = LampiranPengajuan::where('jenis_lampiran', 'Dokumen Pendukung')->first();
        $admin = $this->asRole('admin');

        $g = $this->get("/api/admin/adopsi/{$p->id_pengajuan}/lampiran/{$ktp->id_lampiran}", $admin)->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('image/png', $g->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $g->headers->get('Content-Disposition'));          // gambar: boleh dipratinjau
        $this->assertStringContainsString($p->nomor_pengajuan.'-ktp.png', $g->headers->get('Content-Disposition'));   // nama aman, bukan nama asli
        $this->assertStringContainsString('no-store', $g->headers->get('Cache-Control'));

        $d = $this->get("/api/admin/adopsi/{$p->id_pengajuan}/lampiran/{$pdf->id_lampiran}", $admin)->assertOk();
        $this->assertStringContainsString('attachment', $d->headers->get('Content-Disposition'));      // PDF pihak luar: selalu diunduh

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'LIHAT_LAMPIRAN', 'nama_tabel' => 'lampiran_pengajuan', 'id_data' => $ktp->id_lampiran]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'LIHAT_LAMPIRAN', 'nama_tabel' => 'lampiran_pengajuan', 'id_data' => $pdf->id_lampiran]);
        $this->assertSame(2, AuditLog::where('aktivitas', 'LIHAT_LAMPIRAN')->count());
    }

    public function test_lampiran_milik_pengajuan_lain_atau_berkas_hilang_404_dan_tidak_diaudit(): void
    {
        $this->ajukanPublik([], ['ktp' => UploadedFile::fake()->image('ktp.jpg', 800, 500)])->assertCreated();
        $p = PengajuanAdopsi::first();
        $lampiran = LampiranPengajuan::first();
        $lain = $this->buatPengajuan();
        $admin = $this->asRole('admin');

        $this->get("/api/admin/adopsi/{$lain->id_pengajuan}/lampiran/{$lampiran->id_lampiran}", $admin)->assertStatus(404);   // bukan milik pengajuan itu
        $this->get("/api/admin/adopsi/{$p->id_pengajuan}/lampiran/99999", $admin)->assertStatus(404);

        Storage::disk('lampiran')->delete($lampiran->url_file);   // berkas fisik hilang
        $this->get("/api/admin/adopsi/{$p->id_pengajuan}/lampiran/{$lampiran->id_lampiran}", $admin)->assertStatus(404);

        $this->assertSame(0, AuditLog::where('aktivitas', 'LIHAT_LAMPIRAN')->count());
    }

    public function test_lampiran_tidak_bisa_diunduh_tanpa_login(): void
    {
        $this->ajukanPublik([], ['ktp' => UploadedFile::fake()->image('ktp.jpg', 800, 500)])->assertCreated();
        $p = PengajuanAdopsi::first();
        $lampiran = LampiranPengajuan::first();

        $this->get("/api/admin/adopsi/{$p->id_pengajuan}/lampiran/{$lampiran->id_lampiran}", ['Accept' => 'application/json'])->assertStatus(401);
    }
}

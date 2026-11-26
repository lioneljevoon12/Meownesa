<?php

namespace Tests\Feature;

use App\Models\LampiranPengajuan;
use App\Models\PengajuanAdopsi;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MembuatDataKucing;
use Tests\TestCase;

/** dev-backend-07: pengajuan adopsi publik tanpa akun, kode tracking, lampiran privat. */
class PengajuanAdopsiApiTest extends TestCase
{
    use MembuatDataKucing, RefreshDatabase;

    private $kucing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
        Storage::fake('lampiran');
        $this->kucing = $this->buatKucing(['status_kucing' => 'Tersedia', 'status_steril' => 'Sudah']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function form(array $o = []): array
    {
        return $o + [
            'id_kucing'        => $this->kucing->id_kucing,
            'nama_pemohon'     => 'Dewi Anggraini',
            'nomor_whatsapp'   => '081234567890',
            'email'            => 'dewi@contoh.id',
            'alamat'           => 'Jl. Ketintang Baru No. 12, Surabaya',
            'persetujuan_data' => true,
        ];
    }

    private function ajukan(array $o = [])
    {
        return $this->postJson('/api/adopsi/ajukan', $this->form($o));
    }

    private function pengajuan(string $status = 'MENUNGGU', array $o = []): PengajuanAdopsi
    {
        static $n = 0;
        $n++;

        return PengajuanAdopsi::create($o + [
            'id_kucing' => $this->kucing->id_kucing, 'nomor_pengajuan' => sprintf('ADP-2026-9%02d', $n), 'kode_tracking' => sprintf('MN-TEST-AAAA-%04d', $n),
            'nama_pemohon' => 'Budi Santoso', 'nomor_whatsapp' => '6281111111111', 'email' => "budi{$n}@contoh.id",
            'alamat' => 'Jl. Contoh No. 1', 'status_pengajuan' => $status, 'tanggal_pengajuan' => '2026-11-01',
        ]);
    }

    // ----------------------------------------------------------------- ajukan

    public function test_calon_adopter_mengajukan_tanpa_akun_dan_mendapat_nomor_serta_kode_tracking(): void
    {
        $tahun = Carbon::now('Asia/Jakarta')->format('Y');

        $res = $this->ajukan()->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nomor_pengajuan', "ADP-{$tahun}-001")
            ->assertJsonPath('data.status', 'MENUNGGU')
            ->assertJsonPath('data.status_label', 'Menunggu Pemeriksaan')
            ->assertJsonPath('data.tanggal_pengajuan', Carbon::now('Asia/Jakarta')->toDateString())
            ->assertJsonPath('data.kucing.nama_kucing', $this->kucing->nama_kucing);

        $this->assertMatchesRegularExpression('/^MN-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $res->json('data.kode_tracking'));

        $this->assertDatabaseHas('pengajuan_adopsi', [
            'nomor_pengajuan' => "ADP-{$tahun}-001", 'kode_tracking' => $res->json('data.kode_tracking'),
            'status_pengajuan' => 'MENUNGGU', 'id_adopter' => null, 'id_kucing' => $this->kucing->id_kucing,
        ]);
        $this->assertDatabaseCount('users', 0);   // tidak ada akun yang dibuat (MEOW-BR-06)
    }

    public function test_respons_pengajuan_tidak_memuat_data_pribadi_yang_diinput(): void
    {
        $res = $this->ajukan()->assertCreated();
        $json = json_encode($res->json());

        foreach (['dewi@contoh.id', '6281234567890', '081234567890', 'Jl. Ketintang', 'Anggraini'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json, "Respons membocorkan: {$rahasia}");
        }
        $this->assertSame('Dewi', $res->json('data.nama_depan'));
    }

    public function test_data_dirapikan_saat_disimpan(): void
    {
        $this->ajukan(['nama_pemohon' => "  dewi   anggraini ", 'email' => '  Dewi@Contoh.ID ', 'alamat' => '  Jl. Ketintang Baru No. 12  '])->assertCreated();

        $p = PengajuanAdopsi::first();
        $this->assertSame('dewi anggraini', $p->nama_pemohon);
        $this->assertSame('dewi@contoh.id', $p->email);
        $this->assertSame('Jl. Ketintang Baru No. 12', $p->alamat);
    }

    public function test_nomor_pengajuan_berurutan_dan_mulai_dari_001_tiap_tahun(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 30, 10, 0, 0, 'Asia/Jakarta'));
        $a = $this->ajukan(['email' => 'a@contoh.id', 'nomor_whatsapp' => '081111111101'])->assertCreated()->json('data.nomor_pengajuan');
        $b = $this->ajukan(['email' => 'b@contoh.id', 'nomor_whatsapp' => '081111111102'])->assertCreated()->json('data.nomor_pengajuan');

        Carbon::setTestNow(Carbon::create(2027, 1, 5, 10, 0, 0, 'Asia/Jakarta'));
        $c = $this->ajukan(['email' => 'c@contoh.id', 'nomor_whatsapp' => '081111111103'])->assertCreated()->json('data.nomor_pengajuan');

        $this->assertSame(['ADP-2026-001', 'ADP-2026-002', 'ADP-2027-001'], [$a, $b, $c]);
    }

    public function test_kode_tracking_unik_acak_dan_tidak_membingungkan(): void
    {
        $generator = app(\App\Services\TrackingCodeGenerator::class);
        $kode = collect(range(1, 300))->map(fn () => $generator->kodeTracking());

        $this->assertCount(300, $kode->unique());
        foreach ($kode as $k) {
            $this->assertMatchesRegularExpression('/^MN-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $k);
            $this->assertDoesNotMatchRegularExpression('/[IO01]/', substr($k, 3));
        }
    }

    public function test_nomor_whatsapp_dinormalkan_ke_format_internasional(): void
    {
        foreach (['0812-3456-7890', '+62 812 3456 7890', '6281234567890', '81234567890'] as $i => $varian) {
            // kucing berbeda tiap varian agar tidak terbentur aturan duplikat pelamar
            $kucing = $this->buatKucing(['status_kucing' => 'Tersedia']);
            $this->ajukan(['nomor_whatsapp' => $varian, 'email' => "wa{$i}@contoh.id", 'id_kucing' => $kucing->id_kucing])->assertCreated();
        }
        $this->assertSame(['6281234567890'], PengajuanAdopsi::pluck('nomor_whatsapp')->unique()->values()->all());
    }

    // -------------------------------------------------------------- validasi

    public function test_validasi_formulir(): void
    {
        $this->postJson('/api/adopsi/ajukan', [])->assertStatus(422)
            ->assertJsonValidationErrors(['id_kucing', 'nama_pemohon', 'nomor_whatsapp', 'email', 'alamat', 'persetujuan_data']);

        foreach ([
            ['id_kucing' => 999],
            ['nama_pemohon' => 'Ab'],
            ['nama_pemohon' => 'Dewi 123'],
            ['nama_pemohon' => '<script>alert(1)</script>'],
            ['nomor_whatsapp' => '12345'],
            ['nomor_whatsapp' => '0712345678901'],
            ['nomor_whatsapp' => 'abcdefghij'],
            ['email' => 'bukan-email'],
            ['alamat' => 'pendek'],
            ['alamat' => str_repeat('a', 501)],
            ['persetujuan_data' => false],
        ] as $salah) {
            $this->ajukan($salah)->assertStatus(422);
        }
        $this->assertDatabaseCount('pengajuan_adopsi', 0);
    }

    public function test_nama_dengan_huruf_aksen_titik_dan_apostrof_diterima(): void
    {
        $this->ajukan(['nama_pemohon' => "Siti Nur Aini, S.Pd."])->assertStatus(422);   // koma tidak diizinkan
        $this->ajukan(['nama_pemohon' => "Dewi O'Brien-Ñandú Jr."])->assertCreated();
    }

    // ---------------------------------------------------------- MEOW-BR-14

    public function test_hanya_kucing_tersedia_yang_bisa_diajukan(): void
    {
        foreach (['Terdata', 'Proses Screening', 'Diadopsi', 'Hilang', 'Almarhum'] as $status) {
            $k = $this->buatKucing(['status_kucing' => $status]);
            $this->ajukan(['id_kucing' => $k->id_kucing])->assertStatus(422)->assertJsonValidationErrors('id_kucing');
        }
        $this->assertDatabaseCount('pengajuan_adopsi', 0);
        $this->ajukan()->assertCreated();
    }

    // -------------------------------------------------------------- duplikat

    public function test_pelamar_yang_sama_tidak_boleh_punya_dua_pengajuan_aktif_untuk_kucing_yang_sama(): void
    {
        $this->ajukan()->assertCreated();

        $dup = $this->ajukan(['nomor_whatsapp' => '089999999999'])->assertStatus(422)->assertJsonValidationErrors('email');   // email sama
        $this->ajukan(['email' => 'lain@contoh.id'])->assertStatus(422);                                                       // WhatsApp sama
        $this->assertStringNotContainsString('ADP-', $dup->json('errors.email.0'));                                             // tidak membocorkan nomor
        $this->assertDatabaseCount('pengajuan_adopsi', 1);

        // pelamar lain, atau kucing lain, boleh
        $this->ajukan(['email' => 'lain@contoh.id', 'nomor_whatsapp' => '089999999999'])->assertCreated();
        $lain = $this->buatKucing(['status_kucing' => 'Tersedia']);
        $this->ajukan(['id_kucing' => $lain->id_kucing])->assertCreated();
    }

    public function test_setelah_diputuskan_pelamar_boleh_mengajukan_lagi(): void
    {
        $this->ajukan()->assertCreated();
        PengajuanAdopsi::query()->update(['status_pengajuan' => 'DITOLAK']);

        $this->ajukan()->assertCreated();
    }

    // -------------------------------------------------------------- lampiran

    public function test_lampiran_saat_mengajukan_disimpan_privat_dengan_nama_acak(): void
    {
        $res = $this->post('/api/adopsi/ajukan', $this->form([
            'ktp'               => UploadedFile::fake()->image('ktp-asli-dewi.jpg', 800, 500),
            'foto_rumah'        => UploadedFile::fake()->image('rumah.png', 800, 600),
            'dokumen_pendukung' => UploadedFile::fake()->create('surat-pernyataan.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseCount('lampiran_pengajuan', 3);
        $this->assertSame(['Dokumen Pendukung', 'Foto Rumah', 'KTP'], collect($res->json('data.lampiran'))->pluck('jenis_lampiran')->sort()->values()->all());

        foreach (LampiranPengajuan::all() as $l) {
            $this->assertMatchesRegularExpression('#^\d+/[a-z0-9]{40}\.(jpg|png|pdf)$#', $l->url_file);   // nama acak, bukan nama asli
            Storage::disk('lampiran')->assertExists($l->url_file);
            $this->assertFileDoesNotExist(public_path($l->url_file));                                      // bukan di folder publik
            $this->assertFileDoesNotExist(public_path('storage/'.$l->url_file));
        }
        $this->assertStringNotContainsString('ktp-asli-dewi', json_encode(LampiranPengajuan::pluck('url_file')));
        $this->assertStringNotContainsString('url_file', json_encode($res->json()));                       // jalur berkas tidak dibocorkan
    }

    public function test_lampiran_tidak_valid_membatalkan_seluruh_pengajuan_tanpa_berkas_yatim(): void
    {
        $this->post('/api/adopsi/ajukan', $this->form([
            'ktp'        => UploadedFile::fake()->image('ktp.jpg', 800, 500),
            'foto_rumah' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('foto_rumah');

        $this->assertDatabaseCount('pengajuan_adopsi', 0);
        $this->assertDatabaseCount('lampiran_pengajuan', 0);
        $this->assertSame([], Storage::disk('lampiran')->allFiles());
    }

    public function test_skrip_php_yang_disamarkan_sebagai_jpg_ditolak_berdasarkan_isi_berkas(): void
    {
        // Isi sengaja skrip PHP yang tidak berbahaya: pola webshell sungguhan diblokir antivirus Windows
        // sehingga berkasnya tak bisa dibaca saat tes. Yang diuji hanya "isi = PHP, bukan gambar".
        $tmp = tempnam(sys_get_temp_dir(), 'skr');
        file_put_contents($tmp, "<?php\necho 'bukan gambar';\n");
        $palsu = new UploadedFile($tmp, 'ktp.jpg', 'image/jpeg', null, true);   // nama & tipe klaim = gambar

        $this->post('/api/adopsi/ajukan', $this->form(['ktp' => $palsu]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('ktp');

        $this->assertDatabaseCount('pengajuan_adopsi', 0);
        $this->assertSame([], Storage::disk('lampiran')->allFiles());
        @unlink($tmp);
    }

    public function test_lampiran_terlalu_besar_ditolak(): void
    {
        $this->post('/api/adopsi/ajukan', $this->form(['ktp' => UploadedFile::fake()->image('besar.jpg', 800, 600)->size(6000)]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('ktp');
    }

    // ------------------------------------------------------- upload-lampiran

    private function unggah(string $kode, string $jenis = 'KTP', ?UploadedFile $berkas = null)
    {
        return $this->post('/api/adopsi/upload-lampiran', [
            'kode_tracking' => $kode, 'jenis_lampiran' => $jenis, 'berkas' => $berkas ?? UploadedFile::fake()->image('a.jpg', 600, 400),
        ], ['Accept' => 'application/json']);
    }

    public function test_menambah_lampiran_dengan_kode_tracking(): void
    {
        $kode = $this->ajukan()->assertCreated()->json('data.kode_tracking');

        $res = $this->unggah(strtolower($kode), 'Foto Rumah')->assertCreated()   // huruf kecil tetap diterima
            ->assertJsonPath('data.jenis_lampiran', 'Foto Rumah')
            ->assertJsonPath('data.jumlah_lampiran', 1);

        $l = LampiranPengajuan::find($res->json('data.id_lampiran'));
        $this->assertSame(PengajuanAdopsi::first()->id_pengajuan, $l->id_pengajuan);
        Storage::disk('lampiran')->assertExists($l->url_file);
    }

    public function test_upload_lampiran_kode_tidak_dikenal_404_dan_validasi(): void
    {
        $this->unggah('MN-XXXX-XXXX-XXXX')->assertStatus(404);

        $kode = $this->ajukan()->json('data.kode_tracking');
        $this->unggah($kode, 'Selfie')->assertStatus(422)->assertJsonValidationErrors('jenis_lampiran');
        $this->unggah($kode, 'KTP', UploadedFile::fake()->create('a.pdf', 6000, 'application/pdf'))->assertStatus(422);
        $this->unggah($kode, 'KTP', UploadedFile::fake()->create('a.zip', 10, 'application/zip'))->assertStatus(422);
        $this->post('/api/adopsi/upload-lampiran', ['kode_tracking' => $kode, 'jenis_lampiran' => 'KTP'], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('lampiran_pengajuan', 0);
    }

    public function test_lampiran_maksimal_5_per_pengajuan(): void
    {
        $kode = $this->ajukan()->json('data.kode_tracking');
        foreach (range(1, 5) as $i) {
            $this->unggah($kode)->assertCreated();
        }

        $this->unggah($kode)->assertStatus(422)->assertJsonValidationErrors('berkas');
        $this->assertDatabaseCount('lampiran_pengajuan', 5);
        $this->assertCount(5, Storage::disk('lampiran')->allFiles());   // berkas ke-6 tidak tersimpan
    }

    public function test_lampiran_tidak_bisa_ditambah_setelah_pengajuan_diputuskan(): void
    {
        $kode = $this->ajukan()->json('data.kode_tracking');

        foreach (['DISETUJUI', 'DITOLAK', 'SELESAI'] as $status) {
            PengajuanAdopsi::query()->update(['status_pengajuan' => $status]);
            $this->unggah($kode)->assertStatus(422)->assertJsonValidationErrors('kode_tracking');
        }
        PengajuanAdopsi::query()->update(['status_pengajuan' => 'SCREENING']);
        $this->unggah($kode)->assertCreated();   // masih berjalan = boleh
    }

    // -------------------------------------------------------------- tracking

    public function test_pelacakan_status_dengan_kode_tracking_tanpa_login(): void
    {
        $kode = $this->ajukan()->json('data.kode_tracking');
        $nomor = PengajuanAdopsi::first()->nomor_pengajuan;
        $this->unggah($kode, 'KTP');

        $res = $this->getJson("/api/adopsi/tracking/{$kode}")->assertOk()
            ->assertJsonPath('data.nomor_pengajuan', $nomor)
            ->assertJsonPath('data.status', 'MENUNGGU')
            ->assertJsonPath('data.nama_depan', 'Dewi')
            ->assertJsonPath('data.kucing.kode_kucing', $this->kucing->kode_kucing)
            ->assertJsonCount(1, 'data.lampiran')
            ->assertJsonCount(4, 'data.tahap');

        $this->assertEqualsCanonicalizing(['id_lampiran', 'jenis_lampiran'], array_keys($res->json('data.lampiran.0')));
        $this->getJson('/api/adopsi/tracking/'.strtolower($kode))->assertOk();   // tidak peka huruf besar/kecil
    }

    public function test_pelacakan_tidak_membocorkan_data_pribadi_maupun_internal(): void
    {
        $kode = $this->ajukan()->json('data.kode_tracking');
        $this->unggah($kode);

        $json = json_encode($this->getJson("/api/adopsi/tracking/{$kode}")->assertOk()->json());

        foreach (['dewi@contoh.id', '6281234567890', 'Jl. Ketintang', 'Anggraini', 'url_file', 'screening', 'id_adopter', 'alamat', 'email', 'nomor_whatsapp'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $json, "Pelacakan membocorkan: {$rahasia}");
        }
    }

    public function test_tahap_dan_teks_status_mengikuti_status_pengajuan(): void
    {
        $kode = $this->ajukan()->json('data.kode_tracking');
        $harapan = [
            'MENUNGGU'  => [true, false, false, false],
            'SCREENING' => [true, true, false, false],
            'DISETUJUI' => [true, true, true, false],
            'DITOLAK'   => [true, true, true, false],
            'SELESAI'   => [true, true, true, true],
        ];

        foreach ($harapan as $status => $selesai) {
            PengajuanAdopsi::query()->update(['status_pengajuan' => $status]);
            $res = $this->getJson("/api/adopsi/tracking/{$kode}")->assertOk();

            $this->assertSame($selesai, collect($res->json('data.tahap'))->pluck('selesai')->all(), "Tahap untuk {$status}");
            $this->assertNotEmpty($res->json('data.status_label'));
            $this->assertNotEmpty($res->json('data.keterangan'));
        }
    }

    public function test_nomor_pengajuan_opsional_sebagai_pemeriksaan_tambahan(): void
    {
        $kode = $this->ajukan()->json('data.kode_tracking');
        $nomor = PengajuanAdopsi::first()->nomor_pengajuan;

        $this->getJson("/api/adopsi/tracking/{$kode}?nomor_pengajuan={$nomor}")->assertOk();
        $this->getJson("/api/adopsi/tracking/{$kode}?nomor_pengajuan=".strtolower($nomor))->assertOk();
        $salah = $this->getJson("/api/adopsi/tracking/{$kode}?nomor_pengajuan=ADP-2026-999")->assertStatus(404);
        $tidakAda = $this->getJson('/api/adopsi/tracking/MN-XXXX-XXXX-XXXX')->assertStatus(404);

        // salah nomor tidak dapat dibedakan dari kode yang tidak ada
        $this->assertSame($tidakAda->json(), $salah->json());
        $this->assertFalse($salah->json('success'));
    }

    public function test_pelacakan_dibatasi_30_permintaan_per_menit(): void
    {
        foreach (range(1, 30) as $i) {
            $this->getJson('/api/adopsi/tracking/MN-XXXX-XXXX-XXXX')->assertStatus(404);
        }

        $this->getJson('/api/adopsi/tracking/MN-XXXX-XXXX-XXXX')->assertStatus(429)->assertJsonPath('success', false);
    }

    public function test_pengajuan_dibatasi_30_permintaan_per_jam_per_ip(): void
    {
        foreach (range(1, 30) as $i) {
            $this->postJson('/api/adopsi/ajukan', [])->assertStatus(422);
        }

        $this->postJson('/api/adopsi/ajukan', $this->form())->assertStatus(429);
        $this->assertDatabaseCount('pengajuan_adopsi', 0);
    }

    // ------------------------------------------------------------- isolasi

    public function test_tidak_ada_endpoint_publik_untuk_daftar_pengajuan(): void
    {
        $this->pengajuan();

        $this->getJson('/api/adopsi')->assertStatus(404);
        $this->getJson('/api/adopsi/tracking')->assertStatus(404);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Artikel;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

/**
 * dev-backend-12: CMS artikel edukasi + informasi publik donasi/pelaporan (MEOW-F-33 s/d F-37, BR-10, BR-11).
 * Waktu dibekukan pada 26 Nov 2026 10.00 WIB.
 */
class ArtikelApiTest extends TestCase
{
    use InteractsWithApiAuth, RefreshDatabase;

    private User $penulis;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));
        Storage::fake('artikel_thumbnail');
        $this->penulis = $this->makeUser('admin', 'aktif', 'penulis@uji.test');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function artikel(array $o = []): Artikel
    {
        static $n = 0;
        $n++;

        return Artikel::create($o + [
            'id_user'             => $this->penulis->id_user,
            'judul'               => "Artikel Uji Nomor {$n}",
            'slug'                => "artikel-uji-nomor-{$n}",
            'kategori'            => 'Edukasi & Kampanye',
            'isi'                 => "Isi artikel uji nomor {$n}. Teks ini cukup panjang untuk lolos validasi.",
            'status_publikasi'    => 'PUBLISHED',
            'is_featured'         => false,
            'is_pinned'           => false,
            'tanggal_dibuat'      => '2026-11-01',
            'tanggal_diterbitkan' => '2026-11-01',
        ]);
    }

    private function data(array $o = []): array
    {
        return $o + [
            'judul'    => 'Panduan Merawat Kucing Kampus',
            'kategori' => 'Edukasi & Kampanye',
            'isi'      => 'Kucing kampus perlu makan teratur, vaksin lengkap, dan kalung reflektif agar aman di malam hari.',
        ];
    }

    private function buat(array $o = [], ?array $h = null)
    {
        return $this->postJson('/api/admin/artikel', $this->data($o), $h ?? $this->asRole('admin'));
    }

    // ------------------------------------------------------------ publik: daftar & detail

    public function test_publik_hanya_melihat_artikel_terbit_dengan_yang_disematkan_di_atas(): void
    {
        $lama = $this->artikel(['judul' => 'Lama', 'slug' => 'lama', 'tanggal_diterbitkan' => '2026-10-01']);
        $baru = $this->artikel(['judul' => 'Baru', 'slug' => 'baru', 'tanggal_diterbitkan' => '2026-11-20']);
        $sematan = $this->artikel(['judul' => 'Sematan', 'slug' => 'sematan', 'tanggal_diterbitkan' => '2026-09-01', 'is_pinned' => true]);
        $this->artikel(['judul' => 'Draf', 'slug' => 'draf', 'status_publikasi' => 'DRAFT', 'tanggal_diterbitkan' => null]);
        $this->artikel(['judul' => 'Arsip', 'slug' => 'arsip', 'status_publikasi' => 'ARCHIVED']);

        $res = $this->getJson('/api/artikel')->assertOk()->assertJsonPath('meta.total', 3);

        $this->assertSame(['sematan', 'baru', 'lama'], array_column($res->json('data'), 'slug'));
    }

    public function test_kartu_artikel_publik_tidak_membocorkan_data_internal(): void
    {
        $this->artikel(['slug' => 'dengan-gambar', 'thumbnail_url' => 'images/artikel/stop-kalung1.jpeg', 'isi' => str_repeat('Kalimat panjang. ', 40)]);
        $this->artikel(['slug' => 'tanpa-gambar']);

        $kartu = collect($this->getJson('/api/artikel')->assertOk()->json('data'))->keyBy('slug');

        $this->assertSame('http://localhost/images/artikel/stop-kalung1.jpeg', $kartu['dengan-gambar']['thumbnail_url']);
        $this->assertNull($kartu['tanpa-gambar']['thumbnail_url']);
        $this->assertLessThanOrEqual(160, mb_strlen($kartu['dengan-gambar']['ringkasan']));
        $this->assertSame(
            ['id_artikel', 'judul', 'slug', 'kategori', 'ringkasan', 'thumbnail_url', 'is_featured', 'is_pinned', 'tanggal_diterbitkan'],
            array_keys($kartu['dengan-gambar']),
        );
    }

    public function test_filter_kategori_pencarian_dan_paginasi_publik(): void
    {
        $this->artikel(['judul' => 'Vaksin Rabies', 'slug' => 'vaksin-rabies', 'kategori' => 'Kesehatan Veteriner']);
        $this->artikel(['judul' => 'Sterilisasi', 'slug' => 'sterilisasi', 'kategori' => 'Kesehatan Veteriner', 'isi' => 'Operasi kecil dan aman untuk populasi kucing.']);
        $this->artikel(['judul' => 'Cara Adopsi', 'slug' => 'cara-adopsi', 'kategori' => 'Panduan Adopsi']);

        $this->getJson('/api/artikel?kategori=Kesehatan%20Veteriner')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/artikel?q=rabies')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', 'vaksin-rabies');
        $this->getJson('/api/artikel?q=populasi')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', 'sterilisasi');   // mencari di isi juga
        $this->getJson('/api/artikel?q=%25')->assertJsonPath('meta.total', 0);                                                       // % bukan wildcard
        $this->getJson('/api/artikel?per_page=2&page=2')->assertJsonPath('meta.halaman', 2)->assertJsonPath('meta.halaman_akhir', 2)->assertJsonCount(1, 'data');

        foreach (['kategori=Lain', 'per_page=0', 'per_page=51', 'page=0', 'status=DRAFT', 'featured=1', 'pinned=1'] as $salah) {
            $this->getJson("/api/artikel?{$salah}")->assertStatus(422);   // publik tidak boleh memfilter status/unggulan
        }
    }

    public function test_detail_artikel_by_slug_hanya_untuk_yang_terbit(): void
    {
        $this->artikel(['slug' => 'terbit', 'isi' => 'Isi lengkap artikel terbit yang cukup panjang.']);
        $this->artikel(['slug' => 'draf', 'status_publikasi' => 'DRAFT']);
        $this->artikel(['slug' => 'arsip', 'status_publikasi' => 'ARCHIVED']);

        $this->getJson('/api/artikel/terbit')->assertOk()
            ->assertJsonPath('data.slug', 'terbit')->assertJsonPath('data.isi', 'Isi lengkap artikel terbit yang cukup panjang.')
            ->assertJsonMissingPath('data.penulis')->assertJsonMissingPath('data.status_publikasi');

        foreach (['draf', 'arsip', 'tidak-ada', 'Terbit', 'ter bit'] as $slug) {
            $this->getJson('/api/artikel/'.rawurlencode($slug))->assertNotFound();
        }
    }

    public function test_featured_dan_kategori_publik_tidak_tertelan_rute_slug(): void
    {
        $this->artikel(['slug' => 'unggulan-lama', 'is_featured' => true, 'tanggal_diterbitkan' => '2026-10-01']);
        $this->artikel(['slug' => 'unggulan-sematan', 'is_featured' => true, 'is_pinned' => true, 'tanggal_diterbitkan' => '2026-09-01']);
        $this->artikel(['slug' => 'unggulan-baru', 'is_featured' => true, 'tanggal_diterbitkan' => '2026-11-10']);
        $this->artikel(['slug' => 'biasa']);
        $this->artikel(['slug' => 'unggulan-draf', 'is_featured' => true, 'status_publikasi' => 'DRAFT']);   // di luar invarian, tetap tidak boleh bocor

        $this->getJson('/api/artikel/featured')->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(['unggulan-sematan', 'unggulan-baru', 'unggulan-lama'], array_column($this->getJson('/api/artikel/featured')->json('data'), 'slug'));
        $this->getJson('/api/artikel/featured?limit=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'unggulan-sematan');
        $this->getJson('/api/artikel/featured?limit=0')->assertJsonCount(1, 'data');     // dibatasi 1..10
        $this->getJson('/api/artikel/featured?limit=999')->assertJsonCount(3, 'data');

        $kategori = collect($this->getJson('/api/artikel/kategori')->assertOk()->json('data'))->keyBy('kategori');
        $this->assertSame(4, $kategori['Edukasi & Kampanye']['jumlah']);                  // draf tidak dihitung
        $this->assertSame(0, $kategori['Cerita Kucing']['jumlah']);
        $this->assertSame(Artikel::KATEGORI, $kategori->keys()->all());
    }

    // ------------------------------------------------------------ info publik

    public function test_info_publik_menyajikan_rekening_form_dan_kontak_tanpa_memproses_pembayaran(): void
    {
        config([
            'jagasatwa.donasi' => ['bank' => 'BNI', 'nomor_rekening' => '1234567890', 'atas_nama' => 'Jaga Satwa UNESA', 'qris_url' => 'images/qris.png'],
            'jagasatwa.form_pelaporan_url' => 'https://forms.gle/abc123',
            'jagasatwa.kontak' => ['whatsapp' => '081234567890', 'instagram' => '@jagasatwa.unesa'],
        ]);

        $this->getJson('/api/info-publik')->assertOk()
            ->assertJsonPath('data.donasi.tersedia', true)
            ->assertJsonPath('data.donasi.bank', 'BNI')
            ->assertJsonPath('data.donasi.nomor_rekening', '1234567890')
            ->assertJsonPath('data.donasi.qris_url', 'http://localhost/images/qris.png')
            ->assertJsonPath('data.pelaporan.form_url', 'https://forms.gle/abc123')
            ->assertJsonPath('data.kontak.whatsapp', '6281234567890')
            ->assertJsonPath('data.kontak.whatsapp_tautan', 'https://wa.me/6281234567890')
            ->assertJsonPath('data.kontak.instagram', 'jagasatwa.unesa')
            ->assertJsonPath('data.kontak.instagram_tautan', 'https://instagram.com/jagasatwa.unesa');
        $this->assertStringContainsString('tidak memproses pembayaran', $this->getJson('/api/info-publik')->json('data.donasi.catatan'));   // BR-10
    }

    public function test_info_publik_kosong_bila_belum_dikonfigurasi(): void
    {
        config([
            'jagasatwa.donasi' => ['bank' => '', 'nomor_rekening' => null, 'atas_nama' => '  ', 'qris_url' => null],
            'jagasatwa.form_pelaporan_url' => null,
            'jagasatwa.kontak' => ['whatsapp' => 'bukan-nomor', 'instagram' => ''],
        ]);

        $this->getJson('/api/info-publik')->assertOk()
            ->assertJsonPath('data.donasi.tersedia', false)
            ->assertJsonPath('data.donasi.bank', null)
            ->assertJsonPath('data.donasi.atas_nama', null)
            ->assertJsonPath('data.pelaporan.form_url', null)
            ->assertJsonPath('data.kontak.whatsapp', null)
            ->assertJsonPath('data.kontak.whatsapp_tautan', null)
            ->assertJsonPath('data.kontak.instagram_tautan', null);
    }

    // ------------------------------------------------------------ admin: hak akses

    public function test_semua_endpoint_cms_artikel_hanya_untuk_admin(): void
    {
        $a = $this->artikel();
        $rute = [
            ['getJson', '/api/admin/artikel'],
            ['getJson', "/api/admin/artikel/{$a->id_artikel}"],
            ['postJson', '/api/admin/artikel'],
            ['putJson', "/api/admin/artikel/{$a->id_artikel}"],
            ['deleteJson', "/api/admin/artikel/{$a->id_artikel}"],
            ['postJson', "/api/admin/artikel/{$a->id_artikel}/thumbnail"],
            ['deleteJson', "/api/admin/artikel/{$a->id_artikel}/thumbnail"],
        ];
        $panggil = fn (string $m, string $u, array $h = []) => $m === 'getJson' ? $this->getJson($u, $h) : $this->$m($u, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);
        }

        $this->assertSame(1, Artikel::count());
        $this->assertSame(0, AuditLog::where('nama_tabel', 'artikel')->count());
    }

    // ------------------------------------------------------------ admin: buat

    public function test_admin_membuat_draf_dengan_slug_otomatis_dan_tercatat_di_audit(): void
    {
        $admin = $this->makeUser('admin', 'aktif', 'pembuat@uji.test');
        $res = $this->buat(['judul' => 'Panduan Merawat Kucing Kampus!'], $this->bearer($this->tokenFor($admin)))->assertCreated()
            ->assertJsonPath('data.slug', 'panduan-merawat-kucing-kampus')
            ->assertJsonPath('data.status_publikasi', 'DRAFT')
            ->assertJsonPath('data.tanggal_dibuat', '2026-11-26')
            ->assertJsonPath('data.tanggal_diterbitkan', null)
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonPath('data.penulis.id_user', $admin->id_user)         // penulis = admin yang login
            ->assertJsonPath('data.thumbnail_url', null);

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'artikel', 'id_data' => $res->json('data.id_artikel')]);
        $this->getJson('/api/artikel/panduan-merawat-kucing-kampus')->assertNotFound();    // draf belum tampil di publik
    }

    public function test_slug_otomatis_dibuat_unik_dan_menghindari_kata_tercadang(): void
    {
        $this->assertSame('panduan-merawat-kucing-kampus', $this->buat()->json('data.slug'));
        $this->assertSame('panduan-merawat-kucing-kampus-2', $this->buat()->json('data.slug'));
        $this->assertSame('panduan-merawat-kucing-kampus-3', $this->buat()->json('data.slug'));
        $this->assertSame('featured-artikel', $this->buat(['judul' => 'Featured'])->json('data.slug'));
        $this->assertSame('artikel', $this->buat(['judul' => '!!!!!!'])->json('data.slug'));
    }

    public function test_admin_langsung_menerbitkan_artikel_unggulan_yang_disematkan(): void
    {
        $this->buat(['judul' => 'Kenapa Kucing Pakai Kalung', 'status_publikasi' => 'PUBLISHED', 'is_featured' => true, 'is_pinned' => true, 'slug' => 'kenapa-kalung'])
            ->assertCreated()
            ->assertJsonPath('data.status_publikasi', 'PUBLISHED')
            ->assertJsonPath('data.tanggal_diterbitkan', '2026-11-26')
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.is_pinned', true);

        $this->getJson('/api/artikel/kenapa-kalung')->assertOk();
        $this->getJson('/api/artikel/featured')->assertJsonPath('data.0.slug', 'kenapa-kalung');
    }

    public function test_isi_dibersihkan_dari_tag_html(): void
    {
        $res = $this->buat(['isi' => "<script>alert('xss')</script><b>Kalung reflektif</b> membantu kucing terlihat di malam hari.\r\n<img src=x onerror=alert(1)>Pasang dengan benar."])->assertCreated();

        $isi = $res->json('data.isi');
        $this->assertStringNotContainsString('<', $isi);
        $this->assertStringNotContainsString('alert', $isi);                 // isi blok script ikut terbuang
        $this->assertStringNotContainsString('onerror', $isi);
        $this->assertStringContainsString('Kalung reflektif membantu kucing terlihat di malam hari.', $isi);
        $this->assertStringNotContainsString("\r", $isi);
    }

    public function test_validasi_membuat_artikel(): void
    {
        $h = $this->asRole('admin');
        $this->postJson('/api/admin/artikel', [], $h)->assertStatus(422)->assertJsonValidationErrors(['judul', 'kategori', 'isi']);
        $this->artikel(['slug' => 'sudah-dipakai']);

        foreach ([
            ['judul' => 'abc'],
            ['judul' => str_repeat('a', 201)],
            ['kategori' => 'Lain-lain'],
            ['isi' => 'terlalu pendek'],
            ['isi' => '<p></p><br>'],                                // kosong setelah tag dibuang
            ['isi' => str_repeat('a', 50001)],
            ['status_publikasi' => 'ARCHIVED'],
            ['status_publikasi' => 'TERBIT'],
            ['slug' => 'Slug Salah'],
            ['slug' => 'featured'],
            ['slug' => 'kategori'],
            ['slug' => 'sudah-dipakai'],
            ['is_featured' => 'mungkin'],
            ['is_featured' => true],                                  // draf tidak boleh unggulan
            ['is_pinned' => true, 'status_publikasi' => 'DRAFT'],
        ] as $salah) {
            $this->buat($salah, $h)->assertStatus(422);
        }

        $this->assertSame(1, Artikel::count());
        $this->assertSame(0, AuditLog::where('nama_tabel', 'artikel')->count());
    }

    public function test_membuat_artikel_dengan_thumbnail_multipart(): void
    {
        $res = $this->post('/api/admin/artikel', $this->data([
            'status_publikasi' => 'PUBLISHED', 'is_featured' => 'true', 'is_pinned' => '0',
            'thumbnail' => UploadedFile::fake()->image('cover.png', 3000, 2000),
        ]), $this->asRole('admin') + ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.is_featured', true)->assertJsonPath('data.is_pinned', false);

        $path = Artikel::firstOrFail()->thumbnail_url;
        $this->assertStringStartsWith('images/artikel/upload/thumb-', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertStringStartsWith('http://localhost/images/artikel/upload/thumb-', $res->json('data.thumbnail_url'));

        $isi = Storage::disk('artikel_thumbnail')->get('upload/'.basename($path));
        [$lebar, $tinggi, $tipe] = getimagesizefromstring($isi);
        $this->assertSame(IMAGETYPE_JPEG, $tipe);                      // PNG di-encode ulang menjadi JPEG
        $this->assertSame(1280, max($lebar, $tinggi));                 // dan diperkecil
    }

    public function test_thumbnail_tidak_valid_menggagalkan_pembuatan_tanpa_berkas_yatim(): void
    {
        $h = $this->asRole('admin') + ['Accept' => 'application/json'];

        foreach ([
            UploadedFile::fake()->create('catatan.txt', 10, 'text/plain'),
            UploadedFile::fake()->createWithContent('cover.jpg', '<?php echo 1;'),
            UploadedFile::fake()->image('kecil.jpg', 50, 50),
            UploadedFile::fake()->image('besar.jpg', 800, 800)->size(6000),
        ] as $berkas) {
            $this->post('/api/admin/artikel', $this->data(['thumbnail' => $berkas]), $h)->assertStatus(422)->assertJsonValidationErrors('thumbnail');
        }

        $this->assertSame(0, Artikel::count());
        $this->assertSame([], Storage::disk('artikel_thumbnail')->allFiles());
    }

    // ------------------------------------------------------------ admin: ubah & siklus status

    public function test_ubah_sebagian_menjaga_slug_dan_bidang_lain(): void
    {
        $a = $this->artikel(['judul' => 'Judul Lama', 'slug' => 'judul-lama']);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/artikel/{$a->id_artikel}", ['judul' => 'Judul Baru Sekali', 'kategori' => 'Cerita Kucing'], $h)->assertOk()
            ->assertJsonPath('data.judul', 'Judul Baru Sekali')
            ->assertJsonPath('data.slug', 'judul-lama')                    // URL lama tidak putus
            ->assertJsonPath('data.kategori', 'Cerita Kucing')
            ->assertJsonPath('data.isi', $a->isi)
            ->assertJsonPath('data.status_publikasi', 'PUBLISHED');

        $this->putJson("/api/admin/artikel/{$a->id_artikel}", ['slug' => 'judul-baru'], $h)->assertOk()->assertJsonPath('data.slug', 'judul-baru');
        $this->putJson("/api/admin/artikel/{$a->id_artikel}", ['slug' => null, 'is_pinned' => true], $h)->assertOk()
            ->assertJsonPath('data.slug', 'judul-baru')->assertJsonPath('data.is_pinned', true);
        $this->putJson("/api/admin/artikel/{$a->id_artikel}", [], $h)->assertOk();

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'artikel', 'id_data' => $a->id_artikel]);
    }

    public function test_validasi_mengubah_artikel(): void
    {
        $a = $this->artikel(['slug' => 'milik-saya']);
        $this->artikel(['slug' => 'milik-orang']);
        $draf = $this->artikel(['status_publikasi' => 'DRAFT', 'tanggal_diterbitkan' => null]);
        $h = $this->asRole('admin');

        foreach ([
            ['judul' => 'abc'],
            ['kategori' => 'Lain'],
            ['isi' => 'pendek'],
            ['slug' => 'milik-orang'],
            ['slug' => 'Tidak Valid'],
            ['status_publikasi' => 'DIHAPUS'],
            ['status_publikasi' => 'DRAFT', 'is_featured' => true],
        ] as $salah) {
            $this->putJson("/api/admin/artikel/{$a->id_artikel}", $salah, $h)->assertStatus(422);
        }
        $this->putJson("/api/admin/artikel/{$draf->id_artikel}", ['is_pinned' => true], $h)->assertStatus(422)->assertJsonValidationErrors('is_pinned');
        $this->putJson("/api/admin/artikel/{$a->id_artikel}", ['slug' => 'milik-saya'], $h)->assertOk();   // slug sendiri boleh dikirim ulang
        $this->putJson('/api/admin/artikel/99999', ['judul' => 'Judul valid'], $h)->assertNotFound();

        $this->assertFalse($draf->refresh()->is_pinned);
    }

    public function test_siklus_status_tanggal_terbit_dan_sematan(): void
    {
        $a = $this->artikel(['status_publikasi' => 'DRAFT', 'tanggal_diterbitkan' => null, 'slug' => 'siklus']);
        $h = $this->asRole('admin');
        $uri = "/api/admin/artikel/{$a->id_artikel}";

        // terbit pertama kali: tanggal terbit dicatat
        $this->putJson($uri, ['status_publikasi' => 'PUBLISHED', 'is_featured' => true, 'is_pinned' => true], $h)->assertOk()
            ->assertJsonPath('data.tanggal_diterbitkan', '2026-11-26')->assertJsonPath('data.is_featured', true);
        $this->getJson('/api/artikel/siklus')->assertOk();

        // diarsipkan: hilang dari publik dan dilepas dari unggulan/sematan
        $this->putJson($uri, ['status_publikasi' => 'ARCHIVED'], $h)->assertOk()
            ->assertJsonPath('data.is_featured', false)->assertJsonPath('data.is_pinned', false)->assertJsonPath('data.tanggal_diterbitkan', '2026-11-26');
        $this->getJson('/api/artikel/siklus')->assertNotFound();
        $this->assertSame(0, count($this->getJson('/api/artikel/featured')->json('data')));

        // terbit ulang di hari lain: tanggal terbit pertama dipertahankan
        Carbon::setTestNow(Carbon::create(2026, 12, 5, 9, 0, 0, 'Asia/Jakarta'));
        $this->putJson($uri, ['status_publikasi' => 'PUBLISHED'], $h)->assertOk()->assertJsonPath('data.tanggal_diterbitkan', '2026-11-26');
        $this->getJson('/api/artikel/siklus')->assertOk();

        // ditarik kembali ke draf
        $this->putJson($uri, ['status_publikasi' => 'DRAFT'], $h)->assertOk();
        $this->getJson('/api/artikel/siklus')->assertNotFound();
    }

    // ------------------------------------------------------------ admin: hapus & thumbnail

    public function test_hapus_artikel_menghapus_thumbnail_unggahan_tetapi_tidak_gambar_bawaan(): void
    {
        $h = $this->asRole('admin');
        $id = $this->post('/api/admin/artikel', $this->data(['thumbnail' => UploadedFile::fake()->image('c.jpg', 600, 400)]), $h + ['Accept' => 'application/json'])->assertCreated()->json('data.id_artikel');
        $berkas = basename(Artikel::findOrFail($id)->thumbnail_url);
        Storage::disk('artikel_thumbnail')->assertExists('upload/'.$berkas);

        $this->deleteJson("/api/admin/artikel/{$id}", [], $h)->assertOk()->assertJsonPath('data.id_artikel', $id);

        Storage::disk('artikel_thumbnail')->assertMissing('upload/'.$berkas);
        $this->assertDatabaseMissing('artikel', ['id_artikel' => $id]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'DELETE', 'nama_tabel' => 'artikel', 'id_data' => $id]);

        $bawaan = $this->artikel(['thumbnail_url' => 'images/artikel/stop-kalung1.jpeg']);
        $this->deleteJson("/api/admin/artikel/{$bawaan->id_artikel}", [], $h)->assertOk();
        $this->assertFileExists(public_path('images/artikel/stop-kalung1.jpeg'));   // berkas seeder tidak tersentuh
        $this->deleteJson("/api/admin/artikel/{$id}", [], $h)->assertNotFound();
    }

    public function test_unggah_ganti_dan_hapus_thumbnail(): void
    {
        $a = $this->artikel();
        $h = $this->asRole('admin') + ['Accept' => 'application/json'];
        $uri = "/api/admin/artikel/{$a->id_artikel}/thumbnail";

        $this->post($uri, ['thumbnail' => UploadedFile::fake()->image('satu.jpg', 800, 600)], $h)->assertCreated();
        $pertama = basename($a->refresh()->thumbnail_url);
        Storage::disk('artikel_thumbnail')->assertExists('upload/'.$pertama);

        $this->post($uri, ['thumbnail' => UploadedFile::fake()->image('dua.png', 700, 700)], $h)->assertCreated();
        $kedua = basename($a->refresh()->thumbnail_url);
        $this->assertNotSame($pertama, $kedua);
        Storage::disk('artikel_thumbnail')->assertMissing('upload/'.$pertama);        // berkas lama tidak menumpuk
        $this->assertSame(['upload/'.$kedua], Storage::disk('artikel_thumbnail')->allFiles());

        $this->deleteJson($uri, [], $h)->assertOk()->assertJsonPath('data.thumbnail_url', null);
        $this->assertSame([], Storage::disk('artikel_thumbnail')->allFiles());
        // Tiap aksi tercatat TEPAT SATU kali dengan nama khususnya (bukan juga CREATE/DELETE bawaan).
        $this->assertSame(
            ['UNGGAH_THUMBNAIL', 'UNGGAH_THUMBNAIL', 'HAPUS_THUMBNAIL'],
            AuditLog::where('nama_tabel', 'artikel')->where('id_data', $a->id_artikel)->orderBy('id_audit')->pluck('aktivitas')->all(),
        );
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UNGGAH_THUMBNAIL', 'nama_tabel' => 'artikel', 'id_data' => $a->id_artikel]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'HAPUS_THUMBNAIL', 'nama_tabel' => 'artikel', 'id_data' => $a->id_artikel]);

        // tidak ada thumbnail lagi: idempoten dan tidak dicatat sebagai perubahan
        $sebelum = AuditLog::where('nama_tabel', 'artikel')->count();
        $this->deleteJson($uri, [], $h)->assertOk()->assertJsonPath('data.thumbnail_url', null);
        $this->assertSame($sebelum, AuditLog::where('nama_tabel', 'artikel')->count());
    }

    public function test_unggah_thumbnail_menolak_berkas_tidak_valid_dan_artikel_tidak_ada(): void
    {
        $a = $this->artikel(['thumbnail_url' => 'images/artikel/stop-kalung1.jpeg']);
        $h = $this->asRole('admin') + ['Accept' => 'application/json'];

        $this->post("/api/admin/artikel/{$a->id_artikel}/thumbnail", [], $h)->assertStatus(422)->assertJsonValidationErrors('thumbnail');
        $this->post("/api/admin/artikel/{$a->id_artikel}/thumbnail", ['thumbnail' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf')], $h)->assertStatus(422);
        $this->post('/api/admin/artikel/99999/thumbnail', ['thumbnail' => UploadedFile::fake()->image('a.jpg', 400, 400)], $h)->assertNotFound();

        $this->assertSame('images/artikel/stop-kalung1.jpeg', $a->refresh()->thumbnail_url);   // tidak berubah
        $this->assertSame([], Storage::disk('artikel_thumbnail')->allFiles());
    }

    // ------------------------------------------------------------ admin: daftar & detail

    public function test_daftar_admin_memuat_semua_status_dengan_filter_dan_jumlah_per_status(): void
    {
        $this->artikel(['slug' => 'a1', 'judul' => 'Vaksin Tahunan', 'kategori' => 'Kesehatan Veteriner']);
        $this->artikel(['slug' => 'a2', 'is_featured' => true]);
        $this->artikel(['slug' => 'a3', 'status_publikasi' => 'DRAFT', 'tanggal_diterbitkan' => null, 'kategori' => 'Kesehatan Veteriner']);
        $this->artikel(['slug' => 'a4', 'status_publikasi' => 'ARCHIVED', 'is_pinned' => false]);
        $h = $this->asRole('admin');

        $res = $this->getJson('/api/admin/artikel', $h)->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.jumlah_per_status', ['DRAFT' => 1, 'PUBLISHED' => 2, 'ARCHIVED' => 1]);
        $this->assertSame($this->penulis->nama, $res->json('data.0.penulis.nama'));
        $this->assertArrayNotHasKey('isi', $res->json('data.0'));                      // daftar ringan; isi ada di detail
        $this->assertArrayHasKey('ringkasan', $res->json('data.0'));

        $this->getJson('/api/admin/artikel?status=DRAFT', $h)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', 'a3')
            ->assertJsonPath('meta.jumlah_per_status.PUBLISHED', 2);                    // jumlah per status tidak ikut filter status
        $this->getJson('/api/admin/artikel?kategori=Kesehatan%20Veteriner', $h)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.jumlah_per_status.ARCHIVED', 0);
        $this->getJson('/api/admin/artikel?featured=true', $h)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', 'a2');
        $this->getJson('/api/admin/artikel?featured=false&status=PUBLISHED', $h)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/artikel?q=vaksin', $h)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/artikel?per_page=2&page=2', $h)->assertJsonPath('meta.halaman_akhir', 2)->assertJsonCount(2, 'data');

        foreach (['status=Terbit', 'featured=mungkin', 'kategori=Lain', 'per_page=51'] as $salah) {
            $this->getJson("/api/admin/artikel?{$salah}", $h)->assertStatus(422);
        }
    }

    public function test_detail_admin_memuat_isi_lengkap_untuk_semua_status(): void
    {
        $draf = $this->artikel(['status_publikasi' => 'DRAFT', 'tanggal_diterbitkan' => null, 'isi' => 'Isi draf yang cukup panjang untuk diedit admin.']);
        $h = $this->asRole('admin');

        $this->getJson("/api/admin/artikel/{$draf->id_artikel}", $h)->assertOk()
            ->assertJsonPath('data.isi', 'Isi draf yang cukup panjang untuk diedit admin.')
            ->assertJsonPath('data.status_publikasi', 'DRAFT')
            ->assertJsonPath('data.penulis.nama', $this->penulis->nama);
        $this->getJson('/api/admin/artikel/99999', $h)->assertNotFound();
    }

    public function test_seeder_artikel_terlihat_di_publik_dengan_gambar_yang_ada(): void
    {
        $this->seed(\Database\Seeders\ArtikelSeeder::class);

        $res = $this->getJson('/api/artikel')->assertOk()->assertJsonPath('meta.total', 3);
        $this->assertSame(1, collect($res->json('data'))->where('is_pinned', true)->count());
        foreach (Artikel::whereNotNull('thumbnail_url')->pluck('thumbnail_url') as $url) {
            $this->assertFileExists(public_path($url));   // tidak ada thumbnail seeder yang menunjuk berkas hilang
        }
    }
}

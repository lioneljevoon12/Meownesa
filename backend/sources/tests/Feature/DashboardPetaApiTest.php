<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Kucing;
use App\Models\Lokasi;
use App\Models\Notifikasi;
use App\Models\PascaAdopsi;
use App\Models\PemberianMakan;
use App\Models\PengajuanAdopsi;
use App\Models\SerahTerima;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/**
 * dev-backend-13: statistik dashboard admin dan peta persebaran publik (MEOW-F-38 s/d F-40, NF-09).
 * Waktu dibekukan pada 26 Nov 2026 10.00 WIB.
 */
class DashboardPetaApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, MembuatPengajuan, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function kucingDi(string $status, string $steril = 'Sudah', ?Lokasi $lokasi = null): Kucing
    {
        return $this->buatKucing(['status_kucing' => $status, 'status_steril' => $steril, 'id_lokasi' => ($lokasi ?? $this->danau)->id_lokasi]);
    }

    private function pengajuan(string $status, string $tanggal = '2026-11-25', array $o = []): PengajuanAdopsi
    {
        return $this->buatPengajuan($this->kucingDi('Tersedia'), $status, $o + ['tanggal_pengajuan' => $tanggal]);
    }

    private function adopsi(string $serah, array $updates = []): SerahTerima
    {
        $p = $this->buatPengajuan($this->kucingDi('Diadopsi'), 'SELESAI', ['tanggal_pengajuan' => '2026-04-01']);
        $st = SerahTerima::create(['id_pengajuan' => $p->id_pengajuan, 'tanggal_serah_terima' => $serah, 'status_serah_terima' => 'Selesai']);
        foreach ($updates as [$tanggal, $kondisi]) {
            PascaAdopsi::create(['id_serah_terima' => $st->id_serah_terima, 'tanggal_update' => $tanggal, 'status_kondisi' => $kondisi]);
        }

        return $st;
    }

    private function feeding(Kucing $k, string $tanggal, string $status): void
    {
        PemberianMakan::create([
            'client_uuid' => (string) Str::uuid(), 'id_kucing' => $k->id_kucing, 'id_lokasi' => $k->id_lokasi,
            'tanggal' => $tanggal, 'status_pemberian' => $status,
        ]);
    }

    private ?PengajuanAdopsi $pengajuanNotif = null;

    /** Notifikasi terkait pengajuan (yang tidak terkait pengajuan, mis. undangan akun pengurus, bukan urusan admin). */
    private function notif(string $kanal, string $status): void
    {
        $this->pengajuanNotif ??= $this->pengajuan('DITOLAK');
        Notifikasi::create(['id_pengajuan' => $this->pengajuanNotif->id_pengajuan, 'jenis_notifikasi' => 'Status Adopsi', 'kanal' => $kanal, 'isi_pesan' => 'x', 'status_pengiriman' => $status]);
    }

    // ------------------------------------------------------------ dashboard: hak akses

    public function test_endpoint_dashboard_hanya_untuk_admin_dan_tidak_menulis_audit_log(): void
    {
        foreach (['/api/admin/dashboard/stats', '/api/admin/dashboard/antrean-terbaru'] as $uri) {
            $this->app['auth']->forgetGuards();
            $this->getJson($uri)->assertStatus(401);
            $this->getJson($uri, $this->asRole('superadmin'))->assertStatus(403);
            $this->getJson($uri, $this->asRole('adopter'))->assertStatus(403);
            $this->getJson($uri, $this->asRole('admin'))->assertOk();
        }

        // Hanya membaca: yang tercatat cuma aktivitas login (dari asRole), tidak ada untuk dashboard.
        $this->assertSame(0, AuditLog::where('aktivitas', '!=', 'LOGIN')->count());
    }

    // ------------------------------------------------------------ dashboard: statistik

    public function test_statistik_kosong_bernilai_nol_tanpa_pembagian_dengan_nol(): void
    {
        $this->getJson('/api/admin/dashboard/stats', $this->asRole('admin'))->assertOk()
            ->assertJsonPath('data.tanggal', '2026-11-26')
            ->assertJsonPath('data.metrik.total_kucing.nilai', 0)
            ->assertJsonPath('data.metrik.siap_adopsi.nilai', 0)
            ->assertJsonPath('data.metrik.antrean_review.nilai', 0)
            ->assertJsonPath('data.metrik.feeding_hari_ini.total', 0)
            ->assertJsonPath('data.metrik.persentase_sterilisasi.nilai', 0)
            ->assertJsonPath('data.metrik.pengingat_terlambat.nilai', 0)
            ->assertJsonPath('data.peringatan', []);
    }

    public function test_enam_metrik_dihitung_akurat_dari_data_sumber(): void
    {
        // kucing: 6 di kampus (3 Terdata, 2 Tersedia, 1 Proses Screening) + 4 yang tidak lagi di kampus
        $this->kucingDi('Terdata', 'Sudah');
        $this->kucingDi('Terdata', 'Sudah');
        $this->kucingDi('Terdata', 'Belum');
        $this->kucingDi('Tersedia', 'Sudah');
        $this->kucingDi('Tersedia', 'Tidak Diketahui');
        $this->kucingDi('Proses Screening', 'Belum');
        $this->kucingDi('Diadopsi', 'Sudah');
        $this->kucingDi('Hilang', 'Belum');
        $this->kucingDi('Almarhum', 'Sudah');
        $this->kucingDi('Almarhum', 'Sudah');

        // pengajuan: 3 antre (2 MENUNGGU, 1 SCREENING), 1 DISETUJUI; yang ditolak/selesai tidak dihitung
        $this->pengajuan('MENUNGGU', '2026-11-25');
        $this->pengajuan('MENUNGGU', '2026-11-20');
        $this->pengajuan('SCREENING');
        $this->pengajuan('DISETUJUI');
        $this->pengajuan('DITOLAK');

        // feeding hari ini + kemarin (kemarin tidak ikut)
        $k = $this->kucingDi('Terdata');
        foreach (['Tuntas', 'Tuntas', 'Belum', 'Terjadwal'] as $s) {
            $this->feeding($k, '2026-11-26', $s);
        }
        $this->feeding($k, '2026-11-25', 'Tuntas');

        // pemantauan pasca-adopsi: satu terlambat (jatuh tempo 17 Nov), satu masih aktif
        $this->adopsi('2026-11-10');
        $this->adopsi('2026-11-24');

        $m = $this->getJson('/api/admin/dashboard/stats', $this->asRole('admin'))->assertOk()->json('data.metrik');

        // Kucing: 10 dibuat eksplisit + 5 Tersedia (satu per pengajuan) + 1 Terdata (feeding) + 2 Diadopsi (dua adopsi).
        $this->assertSame(12, $m['total_kucing']['nilai']);                         // Terdata 4 + Tersedia 7 + Proses Screening 1
        $this->assertSame(
            ['Terdata' => 4, 'Tersedia' => 7, 'Proses Screening' => 1, 'Diadopsi' => 3, 'Hilang' => 1, 'Almarhum' => 2],
            $m['total_kucing']['per_status'],
        );
        $this->assertSame(7, $m['siap_adopsi']['nilai']);
        $this->assertSame(['nilai' => 3, 'menunggu' => 2, 'screening' => 1, 'menunggu_serah_terima' => 1], $m['antrean_review']);
        $this->assertSame(['nilai' => 2, 'tuntas' => 2, 'belum' => 1, 'terjadwal' => 1, 'total' => 4], $m['feeding_hari_ini']);
        $this->assertSame(['nilai' => 1, 'pemantauan_aktif' => 2], $m['pengingat_terlambat']);
    }

    public function test_persentase_sterilisasi_konservatif_dan_hanya_untuk_kucing_di_kampus(): void
    {
        $this->kucingDi('Terdata', 'Sudah');
        $this->kucingDi('Tersedia', 'Belum');
        $this->kucingDi('Proses Screening', 'Tidak Diketahui');
        $this->kucingDi('Diadopsi', 'Sudah');        // sudah tidak di kampus: tidak dihitung
        $this->kucingDi('Almarhum', 'Sudah');

        $this->getJson('/api/admin/dashboard/stats', $this->asRole('admin'))->assertOk()
            ->assertJsonPath('data.metrik.persentase_sterilisasi', ['nilai' => 33.3, 'sudah' => 1, 'belum' => 1, 'tidak_diketahui' => 1, 'total' => 3]);
    }

    public function test_metrik_per_status_memuat_semua_status_walau_kosong(): void
    {
        $this->kucingDi('Terdata');

        $per = $this->getJson('/api/admin/dashboard/stats', $this->asRole('admin'))->json('data.metrik.total_kucing.per_status');

        $this->assertSame(Kucing::STATUS, array_keys($per));
        $this->assertSame(1, $per['Terdata']);
        $this->assertSame(0, $per['Almarhum']);
    }

    public function test_peringatan_operasional_berurutan_menurut_tingkat_dan_hanya_yang_perlu(): void
    {
        // pengajuan MENUNGGU 6 hari (perlu perhatian) dan 1 hari (belum)
        $this->pengajuan('MENUNGGU', '2026-11-20');
        $this->pengajuan('MENUNGGU', '2026-11-25');
        // DISETUJUI lama (8 hari) dan baru
        $lama = $this->pengajuan('DISETUJUI');
        DB::table('pengajuan_adopsi')->where('id_pengajuan', $lama->id_pengajuan)->update(['updated_at' => '2026-11-18 00:00:00']);
        $this->pengajuan('DISETUJUI');
        // feeding belum diberi hari ini
        $this->feeding($this->kucingDi('Terdata'), '2026-11-26', 'Belum');
        // pengingat terlambat
        $this->adopsi('2026-11-10');
        // notifikasi
        $this->notif('Email', 'GAGAL');
        $this->notif('WhatsApp', 'PENDING');
        $this->notif('WhatsApp', 'PENDING');
        $this->notif('Email', 'PENDING');       // email menunggu dikirim terjadwal: bukan peringatan
        $this->notif('WhatsApp', 'TERKIRIM');

        $p = collect($this->getJson('/api/admin/dashboard/stats', $this->asRole('admin'))->assertOk()->json('data.peringatan'));

        $this->assertSame(
            ['email_gagal', 'pengajuan_menunggu_lama', 'pengingat_terlambat', 'feeding_belum_diberi', 'serah_terima_tertunda', 'whatsapp_menunggu'],
            $p->pluck('kode')->all(),
        );
        $this->assertSame(['penting', 'peringatan', 'peringatan', 'peringatan', 'info', 'info'], $p->pluck('level')->all());
        $this->assertSame([1, 1, 1, 1, 1, 2], $p->pluck('jumlah')->all());
        $this->assertSame('admin/adopsi?status=MENUNGGU&sort=terlama', $p->firstWhere('kode', 'pengajuan_menunggu_lama')['rujukan']);
        $this->assertSame('admin/feeding?status=Belum&tanggal=2026-11-26', $p->firstWhere('kode', 'feeding_belum_diberi')['rujukan']);
        $this->assertSame('1 email notifikasi gagal terkirim.', $p->firstWhere('kode', 'email_gagal')['pesan']);
    }

    public function test_hari_ini_mengikuti_waktu_wib_bukan_utc(): void
    {
        // 27 Nov 02.00 WIB = 26 Nov 19.00 UTC: bagi WIB sudah hari berikutnya
        Carbon::setTestNow(Carbon::create(2026, 11, 27, 2, 0, 0, 'Asia/Jakarta'));
        $k = $this->kucingDi('Terdata');
        $this->feeding($k, '2026-11-26', 'Tuntas');
        $this->feeding($k, '2026-11-27', 'Tuntas');
        $this->feeding($k, '2026-11-27', 'Belum');

        $this->getJson('/api/admin/dashboard/stats', $this->asRole('admin'))->assertOk()
            ->assertJsonPath('data.tanggal', '2026-11-27')
            ->assertJsonPath('data.metrik.feeding_hari_ini.total', 2)
            ->assertJsonPath('data.metrik.feeding_hari_ini.tuntas', 1);
    }

    public function test_jumlah_kueri_statistik_tetap_kecil_walau_data_banyak(): void
    {
        foreach (range(1, 30) as $i) {
            $this->kucingDi($i % 2 ? 'Terdata' : 'Tersedia', $i % 3 ? 'Sudah' : 'Belum');
        }
        $h = $this->asRole('admin');

        DB::enableQueryLog();
        $this->getJson('/api/admin/dashboard/stats', $h)->assertOk();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(25, $jumlah, "Statistik memakai {$jumlah} kueri (NF-09: harus agregat, bukan per baris).");
    }

    // ------------------------------------------------------------ dashboard: antrean terbaru

    public function test_antrean_terbaru_mengurutkan_menurut_urgensi_lalu_yang_terlama(): void
    {
        $this->pengajuan('DISETUJUI', '2026-10-01', ['nama_pemohon' => 'Disetujui Lama']);
        $this->pengajuan('SCREENING', '2026-11-05', ['nama_pemohon' => 'Screening']);
        $this->pengajuan('MENUNGGU', '2026-11-24', ['nama_pemohon' => 'Menunggu Baru']);
        $this->pengajuan('MENUNGGU', '2026-11-20', ['nama_pemohon' => 'Menunggu Lama']);
        $this->pengajuan('DITOLAK', '2026-09-01');       // tidak butuh tindakan
        $this->pengajuan('SELESAI', '2026-09-01');

        $res = $this->getJson('/api/admin/dashboard/antrean-terbaru', $this->asRole('admin'))->assertOk()->assertJsonCount(4, 'data');

        $this->assertSame(['Menunggu Lama', 'Menunggu Baru', 'Screening', 'Disetujui Lama'], array_column($res->json('data'), 'nama_pemohon'));
        $this->assertSame([6, 2, 21, 56], array_column($res->json('data'), 'hari_sejak_diajukan'));
        $this->assertSame(['Mulai pemeriksaan', 'Mulai pemeriksaan', 'Isi hasil screening', 'Catat serah terima'], array_column($res->json('data'), 'tindakan_berikutnya'));
    }

    public function test_antrean_setelah_screening_diisi_tinggal_diputuskan_dan_tidak_membocorkan_kontak(): void
    {
        $p = $this->pengajuan('SCREENING', '2026-11-22');
        $this->buatScreening($p);

        $res = $this->getJson('/api/admin/dashboard/antrean-terbaru', $this->asRole('admin'))->assertOk();

        $this->assertSame('Putuskan: setujui atau tolak', $res->json('data.0.tindakan_berikutnya'));
        $this->assertSame(
            ['id_pengajuan', 'nomor_pengajuan', 'nama_pemohon', 'kucing', 'status_pengajuan', 'tanggal_pengajuan', 'hari_sejak_diajukan', 'tindakan_berikutnya'],
            array_keys($res->json('data.0')),
        );
        $this->assertSame(['id_kucing', 'kode_kucing', 'nama_kucing'], array_keys($res->json('data.0.kucing')));
    }

    public function test_antrean_terbaru_membatasi_jumlah_dengan_limit(): void
    {
        foreach (range(1, 25) as $i) {
            $this->pengajuan('MENUNGGU', '2026-11-01');
        }
        $h = $this->asRole('admin');

        $this->getJson('/api/admin/dashboard/antrean-terbaru', $h)->assertJsonCount(5, 'data');          // bawaan
        $this->getJson('/api/admin/dashboard/antrean-terbaru?limit=2', $h)->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/dashboard/antrean-terbaru?limit=0', $h)->assertJsonCount(1, 'data');   // dibatasi 1..20
        $this->getJson('/api/admin/dashboard/antrean-terbaru?limit=999', $h)->assertJsonCount(20, 'data');
    }

    // ------------------------------------------------------------ peta publik

    public function test_peta_publik_menghitung_hanya_kucing_yang_tampil_di_katalog(): void
    {
        $this->kucingDi('Terdata');
        $this->kucingDi('Tersedia');
        $this->kucingDi('Tersedia');
        $this->kucingDi('Proses Screening');     // tidak tampil di katalog publik, tidak dihitung
        $this->kucingDi('Diadopsi');
        $this->kucingDi('Hilang');
        $this->kucingDi('Almarhum');
        $this->kucingDi('Tersedia', 'Sudah', $this->fip);

        $res = $this->getJson('/api/peta/persebaran')->assertOk()           // tanpa login
            ->assertJsonPath('meta.total_titik', 2)->assertJsonPath('meta.tanpa_koordinat', 0);
        $titik = collect($res->json('data.titik'))->keyBy('nama_lokasi');

        $this->assertSame(['id_lokasi' => $this->danau->id_lokasi, 'nama_lokasi' => 'Danau', 'id_kampus' => $this->ktt->id_kampus, 'nama_kampus' => 'Kampus Ketintang',
            'lat' => -7.31, 'lng' => 112.72, 'jumlah_kucing' => 3, 'jumlah_siap_adopsi' => 2], $titik['Danau']);
        $this->assertSame(1, $titik['FIP']['jumlah_kucing']);
        $this->assertSame(1, $titik['FIP']['jumlah_siap_adopsi']);
        $this->assertSame('max-age=60, public', $this->getJson('/api/peta/persebaran')->headers->get('Cache-Control'));
    }

    public function test_lokasi_tanpa_koordinat_valid_tidak_dipetakan_tetapi_dihitung(): void
    {
        Lokasi::create(['id_kampus' => $this->ktt->id_kampus, 'nama_lokasi' => 'Belum Diisi', 'koordinat_x' => 0, 'koordinat_y' => 0]);
        Lokasi::create(['id_kampus' => $this->ktt->id_kampus, 'nama_lokasi' => 'Rusak', 'koordinat_x' => 120.5, 'koordinat_y' => 112.7]);   // lintang di luar rentang

        $res = $this->getJson('/api/peta/persebaran')->assertOk()->assertJsonPath('meta.total_titik', 2)->assertJsonPath('meta.tanpa_koordinat', 2);

        $this->assertSame(['Danau', 'FIP'], collect($res->json('data.titik'))->pluck('nama_lokasi')->sort()->values()->all());
    }

    public function test_ringkasan_kampus_memuat_titik_tengah_dan_jumlah(): void
    {
        Lokasi::create(['id_kampus' => $this->ktt->id_kampus, 'nama_lokasi' => 'Rektorat', 'koordinat_x' => -7.33, 'koordinat_y' => 112.74]);
        $kosong = \App\Models\Kampus::create(['nama_kampus' => 'Kampus Baru']);
        $this->kucingDi('Tersedia');
        $this->kucingDi('Terdata', 'Sudah', Lokasi::where('nama_lokasi', 'Rektorat')->first());

        $kampus = collect($this->getJson('/api/peta/persebaran')->assertOk()->json('data.kampus'))->keyBy('nama_kampus');

        $this->assertEqualsWithDelta(-7.32, $kampus['Kampus Ketintang']['pusat']['lat'], 0.0001);     // rata-rata Danau & Rektorat
        $this->assertEqualsWithDelta(112.73, $kampus['Kampus Ketintang']['pusat']['lng'], 0.0001);
        $this->assertSame(2, $kampus['Kampus Ketintang']['jumlah_lokasi']);
        $this->assertSame(2, $kampus['Kampus Ketintang']['jumlah_kucing']);
        $this->assertNull($kampus['Kampus Baru']['pusat']);                                           // belum ada titik valid
        $this->assertSame(0, $kampus['Kampus Baru']['jumlah_lokasi']);
        $this->assertCount(3, $kampus);
        unset($kosong);
    }

    public function test_peta_filter_per_kampus_dan_validasi(): void
    {
        $this->kucingDi('Tersedia');
        $this->kucingDi('Tersedia', 'Sudah', $this->fip);

        $res = $this->getJson('/api/peta/persebaran?id_kampus='.$this->lwt->id_kampus)->assertOk()->assertJsonPath('meta.total_titik', 1);
        $this->assertSame('FIP', $res->json('data.titik.0.nama_lokasi'));
        $this->assertCount(1, $res->json('data.kampus'));

        foreach (['id_kampus=999', 'id_kampus=abc', 'format=xml'] as $salah) {
            $this->getJson("/api/peta/persebaran?{$salah}")->assertStatus(422);
        }
    }

    public function test_peta_format_geojson_memakai_urutan_bujur_lintang(): void
    {
        $this->kucingDi('Tersedia');

        $res = $this->getJson('/api/peta/persebaran?format=geojson')->assertOk()
            ->assertJsonPath('data.type', 'FeatureCollection')->assertJsonCount(2, 'data.features');
        $danau = collect($res->json('data.features'))->firstWhere('properties.nama_lokasi', 'Danau');

        $this->assertSame('Feature', $danau['type']);
        $this->assertSame('Point', $danau['geometry']['type']);
        $this->assertSame([112.72, -7.31], $danau['geometry']['coordinates']);      // [lng, lat] sesuai standar GeoJSON
        $this->assertSame(1, $danau['properties']['jumlah_kucing']);
        $this->assertArrayNotHasKey('lat', $danau['properties']);
    }

    public function test_peta_tidak_membocorkan_data_adopter_atau_kucing_individual(): void
    {
        $k = $this->kucingDi('Proses Screening');
        $this->buatPengajuan($k, 'SCREENING', ['nama_pemohon' => 'Rahasia Pemohon', 'email' => 'rahasia@contoh.id', 'alamat' => 'Jl. Rahasia 9']);
        $this->kucingDi('Tersedia');

        $teks = $this->getJson('/api/peta/persebaran')->assertOk()->getContent();

        foreach (['Rahasia', 'rahasia@contoh.id', 'alamat', 'nomor_whatsapp', 'kode_kucing', 'nama_kucing'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $teks);
        }
    }

    public function test_peta_tanpa_lokasi_mengembalikan_daftar_kosong(): void
    {
        Lokasi::query()->delete();

        $this->getJson('/api/peta/persebaran')->assertOk()
            ->assertJsonPath('data.titik', [])->assertJsonPath('meta.total_titik', 0)->assertJsonPath('meta.tanpa_koordinat', 0);
    }
}

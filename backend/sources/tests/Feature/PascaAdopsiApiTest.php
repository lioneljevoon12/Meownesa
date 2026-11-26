<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PascaAdopsi;
use App\Models\SerahTerima;
use App\Services\PemantauanPascaAdopsiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/**
 * dev-backend-10: pemantauan kondisi kucing setelah diadopsi (MEOW-F-26 s/d F-28, BR-13).
 * Waktu dibekukan pada 26 Nov 2026 10.00 WIB agar perhitungan jatuh tempo deterministik.
 */
class PascaAdopsiApiTest extends TestCase
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

    /** Adopsi yang sudah diserahterimakan, dengan riwayat kondisi: [[tanggal, kondisi, catatan?], ...]. */
    private function adopsi(string $serah, array $updates = [], array $pengajuan = []): SerahTerima
    {
        $kucing = $this->buatKucing(['status_kucing' => 'Diadopsi']);
        $p = $this->buatPengajuan($kucing, 'SELESAI', $pengajuan + ['tanggal_pengajuan' => '2026-04-01']);
        $st = SerahTerima::create(['id_pengajuan' => $p->id_pengajuan, 'tanggal_serah_terima' => $serah, 'status_serah_terima' => 'Selesai']);

        foreach ($updates as [$tanggal, $kondisi, $catatan]) {
            PascaAdopsi::create(['id_serah_terima' => $st->id_serah_terima, 'tanggal_update' => $tanggal, 'status_kondisi' => $kondisi, 'catatan' => $catatan ?? null]);
        }

        return $st;
    }

    private function catat(SerahTerima $st, array $o = [], ?array $h = null)
    {
        return $this->postJson('/api/admin/pasca-adopsi', $o + [
            'id_serah_terima' => $st->id_serah_terima, 'tanggal_update' => '2026-11-25', 'status_kondisi' => 'Sehat', 'catatan' => 'Aktif makan.',
        ], $h ?? $this->asRole('admin'));
    }

    // ------------------------------------------------------------ hak akses

    public function test_semua_endpoint_pasca_adopsi_hanya_untuk_admin(): void
    {
        $st = $this->adopsi('2026-11-20', [['2026-11-22', 'Sehat', null]]);
        $id = PascaAdopsi::first()->id_pasca_adopsi;
        $rute = [
            ['getJson', '/api/admin/pasca-adopsi'],
            ['getJson', "/api/admin/pasca-adopsi/{$st->id_serah_terima}/riwayat"],
            ['postJson', '/api/admin/pasca-adopsi'],
            ['putJson', "/api/admin/pasca-adopsi/{$id}"],
            ['deleteJson', "/api/admin/pasca-adopsi/{$id}"],
        ];
        $panggil = fn (string $m, string $u, array $h = []) => $m === 'getJson' ? $this->getJson($u, $h) : $this->$m($u, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);   // adopter tidak mengelola pemantauan (BR-13)
        }
        $this->assertDatabaseCount('pasca_adopsi', 1);
    }

    // ----------------------------------------------------------------- catat

    public function test_admin_mencatat_kondisi_dan_mendapat_jadwal_berikutnya(): void
    {
        $st = $this->adopsi('2026-11-20');

        $res = $this->catat($st, ['tanggal_update' => '2026-11-25', 'status_kondisi' => 'Sehat', 'catatan' => '  Lincah, nafsu makan baik  '])->assertCreated()
            ->assertJsonPath('data.status_kondisi', 'Sehat')
            ->assertJsonPath('data.catatan', 'Lincah, nafsu makan baik')
            ->assertJsonPath('data.pemantauan.update_terakhir.tanggal_update', '2026-11-25')
            ->assertJsonPath('data.pemantauan.jatuh_tempo', '2026-12-02')          // +7 hari dari pembaruan terakhir
            ->assertJsonPath('data.pemantauan.status_pemantauan', 'aktif')
            ->assertJsonPath('data.pemantauan.jumlah_update', 1);

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'pasca_adopsi', 'id_data' => $res->json('data.id_pasca_adopsi')]);
    }

    public function test_beberapa_pembaruan_pada_hari_yang_sama_diperbolehkan(): void
    {
        $st = $this->adopsi('2026-11-20');

        $this->catat($st, ['status_kondisi' => 'Sakit', 'catatan' => 'Bersin-bersin'])->assertCreated();
        $this->catat($st, ['status_kondisi' => 'Perawatan', 'catatan' => 'Sudah ke dokter hewan'])->assertCreated();

        $this->assertSame(2, PascaAdopsi::where('id_serah_terima', $st->id_serah_terima)->count());
    }

    public function test_validasi_catat_kondisi(): void
    {
        $st = $this->adopsi('2026-11-20');
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/pasca-adopsi', [], $h)->assertStatus(422)->assertJsonValidationErrors(['id_serah_terima', 'tanggal_update', 'status_kondisi']);

        foreach ([
            ['id_serah_terima' => 999],
            ['status_kondisi' => 'Bahagia'],
            ['tanggal_update' => '25-11-2026'],
            ['tanggal_update' => '2026-11-27'],            // masa depan (WIB)
            ['tanggal_update' => '2026-11-19'],            // sebelum serah terima
            ['catatan' => str_repeat('a', 2001)],
        ] as $salah) {
            $this->catat($st, $salah, $h)->assertStatus(422);
        }
        $this->assertDatabaseCount('pasca_adopsi', 0);
        $this->assertSame(0, AuditLog::where('nama_tabel', 'pasca_adopsi')->count());
    }

    public function test_kondisi_selain_sehat_wajib_dijelaskan_di_catatan(): void
    {
        $st = $this->adopsi('2026-11-20');
        $h = $this->asRole('admin');

        foreach (['Perawatan', 'Sakit', 'Hilang', 'Meninggal'] as $kondisi) {
            $this->catat($st, ['status_kondisi' => $kondisi, 'catatan' => null], $h)->assertStatus(422)->assertJsonValidationErrors('catatan');
            $this->catat($st, ['status_kondisi' => $kondisi, 'catatan' => '   '], $h)->assertStatus(422);
        }
        $this->assertDatabaseCount('pasca_adopsi', 0);

        $this->catat($st, ['status_kondisi' => 'Sehat', 'catatan' => null], $h)->assertCreated();   // Sehat boleh tanpa catatan
    }

    public function test_hari_serah_terima_dan_hari_ini_adalah_batas_tanggal_yang_diterima(): void
    {
        $st = $this->adopsi('2026-11-20');
        $h = $this->asRole('admin');

        $this->catat($st, ['tanggal_update' => '2026-11-20'], $h)->assertCreated();   // hari serah terima
        $this->catat($st, ['tanggal_update' => '2026-11-26'], $h)->assertCreated();   // hari ini (WIB)
    }

    // ------------------------------------------------------- pemantauan berakhir

    public function test_kucing_meninggal_mengakhiri_pemantauan_dan_menolak_pembaruan_baru(): void
    {
        $st = $this->adopsi('2026-11-01', [['2026-11-05', 'Sehat', null]]);
        $h = $this->asRole('admin');

        $res = $this->catat($st, ['tanggal_update' => '2026-11-20', 'status_kondisi' => 'Meninggal', 'catatan' => 'Sakit mendadak, dimakamkan di halaman rumah.'], $h)->assertCreated()
            ->assertJsonPath('data.pemantauan.status_pemantauan', 'berakhir')
            ->assertJsonPath('data.pemantauan.terlambat_hari', 0);

        $this->catat($st, ['tanggal_update' => '2026-11-25'], $h)->assertStatus(422)->assertJsonValidationErrors('id_serah_terima');

        // salah catat -> hapus catatan terakhir, pemantauan berlanjut
        $this->deleteJson('/api/admin/pasca-adopsi/'.$res->json('data.id_pasca_adopsi'), [], $h)->assertOk();
        $this->catat($st, ['tanggal_update' => '2026-11-25'], $h)->assertCreated()->assertJsonPath('data.pemantauan.status_pemantauan', 'aktif');
    }

    public function test_kucing_hilang_tidak_mengakhiri_pemantauan(): void
    {
        $st = $this->adopsi('2026-11-01');
        $h = $this->asRole('admin');

        $this->catat($st, ['status_kondisi' => 'Hilang', 'catatan' => 'Kabur dari rumah, sedang dicari.'], $h)->assertCreated()
            ->assertJsonPath('data.pemantauan.status_pemantauan', 'aktif');
        $this->catat($st, ['status_kondisi' => 'Sehat', 'catatan' => 'Ditemukan kembali.'], $h)->assertCreated();   // bisa ditemukan lagi
    }

    // ---------------------------------------------------------------- daftar

    private function isiSkenario(): array
    {
        // hari ini = 2026-11-26
        return [
            'A' => $this->adopsi('2026-11-23', [], ['nama_pemohon' => 'Adopter Alfa']),                                              // jatuh tempo 11-30 -> aktif
            'B' => $this->adopsi('2026-11-16', [], ['nama_pemohon' => 'Adopter Bravo']),                                             // jatuh tempo 11-23 -> terlambat 3 hari
            'C' => $this->adopsi('2026-11-06', [['2026-11-24', 'Sehat', null]], ['nama_pemohon' => 'Adopter Charlie']),              // jatuh tempo 12-01 -> aktif
            'D' => $this->adopsi('2026-05-20', [['2026-05-25', 'Sehat', null]], ['nama_pemohon' => 'Adopter Delta']),                // masa 6 bulan lewat -> selesai
            'E' => $this->adopsi('2026-10-27', [['2026-11-01', 'Sehat', null], ['2026-11-10', 'Meninggal', 'Sakit parah']], ['nama_pemohon' => 'Adopter Echo']),   // berakhir
            'F' => $this->adopsi('2026-11-01', [['2026-11-18', 'Sakit', 'Flu'], ['2026-11-20', 'Perawatan', 'Obat dari dokter']], ['nama_pemohon' => 'Adopter Foxtrot']),  // jatuh tempo 11-27 -> aktif
            'G' => $this->adopsi('2026-11-01', [['2026-11-10', 'Sehat', null]], ['nama_pemohon' => 'Adopter Golf']),                 // jatuh tempo 11-17 -> terlambat 9 hari
        ];
    }

    public function test_status_pemantauan_dan_jatuh_tempo_dihitung_dengan_benar(): void
    {
        $s = $this->isiSkenario();
        $res = $this->getJson('/api/admin/pasca-adopsi?per_page=100', $this->asRole('admin'))->assertOk();
        $per = collect($res->json('data'))->keyBy('id_serah_terima');

        $harapan = [
            'A' => ['aktif', '2026-11-30', 0],
            'B' => ['terlambat', '2026-11-23', 3],
            'C' => ['aktif', '2026-12-01', 0],
            'D' => ['selesai', '2026-06-01', 0],
            'E' => ['berakhir', '2026-11-17', 0],
            'F' => ['aktif', '2026-11-27', 0],
            'G' => ['terlambat', '2026-11-17', 9],
        ];
        foreach ($harapan as $kunci => [$status, $jatuhTempo, $terlambat]) {
            $r = $per[$s[$kunci]->id_serah_terima];
            $this->assertSame($status, $r['status_pemantauan'], "status {$kunci}");
            $this->assertSame($jatuhTempo, $r['jatuh_tempo'], "jatuh tempo {$kunci}");
            $this->assertSame($terlambat, $r['terlambat_hari'], "terlambat {$kunci}");
        }

        $f = $per[$s['F']->id_serah_terima];
        $this->assertSame('Perawatan', $f['kondisi_terkini']);                       // yang terbaru pada tanggal sama menang
        $this->assertSame(2, $f['jumlah_update']);
        $this->assertSame('2026-11-20', $f['update_terakhir']['tanggal_update']);
        $this->assertNull($per[$s['A']->id_serah_terima]['update_terakhir']);
        $this->assertNull($per[$s['A']->id_serah_terima]['kondisi_terkini']);
        $this->assertSame('2026-11-23', $per[$s['A']->id_serah_terima]['tanggal_serah_terima']);
        $this->assertSame('2027-05-23', $per[$s['A']->id_serah_terima]['masa_pemantauan_sampai']);
    }

    public function test_daftar_diurutkan_dari_yang_paling_terlambat_lalu_yang_segera_jatuh_tempo(): void
    {
        $s = $this->isiSkenario();

        $urut = collect($this->getJson('/api/admin/pasca-adopsi?per_page=100', $this->asRole('admin'))->json('data'))->pluck('id_serah_terima')->all();

        $ids = fn (array $kunci) => array_map(fn ($k) => $s[$k]->id_serah_terima, $kunci);
        // terlambat (G 9 hari, B 3 hari) -> aktif menurut jatuh tempo (F 11-27, A 11-30, C 12-01) -> selesai (D) -> berakhir (E)
        $this->assertSame($ids(['G', 'B', 'F', 'A', 'C', 'D', 'E']), $urut);
    }

    public function test_daftar_memuat_kontak_adopter_untuk_admin_dan_jumlah_per_status(): void
    {
        $this->isiSkenario();

        $res = $this->getJson('/api/admin/pasca-adopsi?status=terlambat', $this->asRole('admin'))->assertOk();

        $this->assertSame(['aktif' => 3, 'terlambat' => 2, 'selesai' => 1, 'berakhir' => 1], $res->json('meta.jumlah_per_status'));   // tidak terpengaruh filter
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertEqualsCanonicalizing(['nama', 'nomor_whatsapp', 'email'], array_keys($res->json('data.0.adopter')));
        $this->assertEqualsCanonicalizing(['id_kucing', 'kode_kucing', 'nama_kucing'], array_keys($res->json('data.0.kucing')));
    }

    public function test_filter_kondisi_pencarian_dan_urutan(): void
    {
        $s = $this->isiSkenario();
        $h = $this->asRole('admin');
        $ids = fn (string $q) => collect($this->getJson("/api/admin/pasca-adopsi?per_page=100&{$q}", $h)->assertOk()->json('data'))->pluck('id_serah_terima')->all();

        $this->assertSame([$s['F']->id_serah_terima], $ids('kondisi=Perawatan'));
        $this->assertSame([$s['E']->id_serah_terima], $ids('kondisi=Meninggal'));
        $this->assertSame([$s['B']->id_serah_terima], $ids('q=bravo'));
        $this->assertSame([$s['A']->id_serah_terima], $ids('q='.$s['A']->pengajuanAdopsi->email));
        $this->assertSame([$s['C']->id_serah_terima], $ids('q='.$s['C']->pengajuanAdopsi->kucing->kode_kucing));
        $this->assertSame([], $ids('q=tidak-ada-sama-sekali'));
        $this->assertSame([$s['A']->id_serah_terima], array_slice($ids('sort=terbaru'), 0, 1));    // serah terima paling baru
        $this->assertSame([$s['D']->id_serah_terima], array_slice($ids('sort=terlama'), 0, 1));    // paling lama
    }

    public function test_paginasi_dan_filter_tidak_valid(): void
    {
        $this->isiSkenario();
        $h = $this->asRole('admin');

        $this->getJson('/api/admin/pasca-adopsi?per_page=3&page=3', $h)->assertOk()
            ->assertJsonPath('meta.halaman', 3)->assertJsonPath('meta.halaman_akhir', 3)->assertJsonPath('meta.total', 7)->assertJsonCount(1, 'data');

        foreach (['status=apa', 'kondisi=Bahagia', 'sort=acak', 'per_page=101', 'page=0'] as $q) {
            $this->getJson("/api/admin/pasca-adopsi?{$q}", $h)->assertStatus(422);
        }
    }

    public function test_hanya_adopsi_yang_sudah_diserahterimakan_masuk_pemantauan(): void
    {
        $kucing = $this->buatKucing(['status_kucing' => 'Proses Screening']);
        $this->buatPengajuan($kucing, 'DISETUJUI');   // disetujui tetapi belum diserahterimakan

        $this->getJson('/api/admin/pasca-adopsi', $this->asRole('admin'))->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
    }

    // ----------------------------------------------------------- batas waktu

    public function test_batas_jatuh_tempo_tepat_7_hari_belum_terlambat_hari_berikutnya_terlambat(): void
    {
        $st = $this->adopsi('2026-11-19');   // jatuh tempo = hari ini (26 Nov)
        $h = $this->asRole('admin');

        $this->getJson('/api/admin/pasca-adopsi', $h)->assertJsonPath('data.0.status_pemantauan', 'aktif')->assertJsonPath('data.0.jatuh_tempo', '2026-11-26');

        Carbon::setTestNow(Carbon::create(2026, 11, 27, 0, 30, 0, 'Asia/Jakarta'));   // lewat tengah malam WIB
        $this->getJson('/api/admin/pasca-adopsi', $h)->assertJsonPath('data.0.status_pemantauan', 'terlambat')->assertJsonPath('data.0.terlambat_hari', 1);
    }

    public function test_masa_pemantauan_6_bulan_berakhir_sehari_setelah_tanggal_terakhir(): void
    {
        $this->adopsi('2026-05-26');   // masa sampai 2026-11-26
        $h = $this->asRole('admin');

        $this->getJson('/api/admin/pasca-adopsi', $h)->assertJsonPath('data.0.masa_pemantauan_sampai', '2026-11-26')
            ->assertJsonPath('data.0.status_pemantauan', 'terlambat');   // hari terakhir masa: masih dipantau

        Carbon::setTestNow(Carbon::create(2026, 11, 27, 9, 0, 0, 'Asia/Jakarta'));
        $this->getJson('/api/admin/pasca-adopsi', $h)->assertJsonPath('data.0.status_pemantauan', 'selesai')->assertJsonPath('data.0.terlambat_hari', 0);
    }

    public function test_layanan_terlambat_menyediakan_bahan_pengingat(): void
    {
        $s = $this->isiSkenario();

        $terlambat = app(PemantauanPascaAdopsiService::class)->terlambat();

        $this->assertEqualsCanonicalizing([$s['B']->id_serah_terima, $s['G']->id_serah_terima], $terlambat->pluck('id_serah_terima')->all());
    }

    // ---------------------------------------------------------------- riwayat

    public function test_riwayat_urut_dari_yang_terbaru_dan_menunjukkan_kondisi_terkini(): void
    {
        $st = $this->adopsi('2026-11-01', [
            ['2026-11-05', 'Sehat', 'Minggu pertama'],
            ['2026-11-20', 'Sakit', 'Flu'],
            ['2026-11-20', 'Perawatan', 'Obat dokter'],   // tanggal sama, dicatat belakangan -> di atas
            ['2026-11-12', 'Sehat', 'Minggu kedua'],
        ]);

        $res = $this->getJson("/api/admin/pasca-adopsi/{$st->id_serah_terima}/riwayat", $this->asRole('admin'))->assertOk();

        $this->assertSame(['Perawatan', 'Sakit', 'Sehat', 'Sehat'], collect($res->json('data.riwayat'))->pluck('status_kondisi')->all());
        $this->assertSame(['2026-11-20', '2026-11-20', '2026-11-12', '2026-11-05'], collect($res->json('data.riwayat'))->pluck('tanggal_update')->all());
        $this->assertSame('Perawatan', $res->json('data.pemantauan.kondisi_terkini'));
        $this->assertSame(4, $res->json('data.pemantauan.jumlah_update'));
        $this->assertEqualsCanonicalizing(['id_pasca_adopsi', 'id_serah_terima', 'tanggal_update', 'status_kondisi', 'catatan', 'dicatat_pada'], array_keys($res->json('data.riwayat.0')));
    }

    public function test_riwayat_kosong_dan_tidak_ada_404(): void
    {
        $st = $this->adopsi('2026-11-20');
        $h = $this->asRole('admin');

        $this->getJson("/api/admin/pasca-adopsi/{$st->id_serah_terima}/riwayat", $h)->assertOk()->assertJsonCount(0, 'data.riwayat')->assertJsonPath('data.pemantauan.kondisi_terkini', null);
        $this->getJson('/api/admin/pasca-adopsi/99999/riwayat', $h)->assertStatus(404);
    }

    // ------------------------------------------------------------ ubah & hapus

    public function test_koreksi_catatan_kondisi_dan_audit(): void
    {
        $st = $this->adopsi('2026-11-01', [['2026-11-10', 'Sehat', 'Awal']]);
        $id = PascaAdopsi::first()->id_pasca_adopsi;
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/pasca-adopsi/{$id}", ['status_kondisi' => 'Sakit', 'catatan' => 'Ternyata flu ringan', 'tanggal_update' => '2026-11-12'], $h)->assertOk()
            ->assertJsonPath('data.status_kondisi', 'Sakit')
            ->assertJsonPath('data.tanggal_update', '2026-11-12')
            ->assertJsonPath('data.pemantauan.kondisi_terkini', 'Sakit')
            ->assertJsonPath('data.pemantauan.jatuh_tempo', '2026-11-19');

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'pasca_adopsi', 'id_data' => $id]);
    }

    public function test_koreksi_menerapkan_aturan_yang_sama(): void
    {
        $st = $this->adopsi('2026-11-10', [['2026-11-15', 'Sehat', null]]);
        $id = PascaAdopsi::first()->id_pasca_adopsi;
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/pasca-adopsi/{$id}", ['status_kondisi' => 'Sakit'], $h)->assertStatus(422)->assertJsonValidationErrors('catatan');   // kondisi diubah tanpa catatan
        $this->putJson("/api/admin/pasca-adopsi/{$id}", ['tanggal_update' => '2026-11-09'], $h)->assertStatus(422)->assertJsonValidationErrors('tanggal_update');   // sebelum serah terima
        $this->putJson("/api/admin/pasca-adopsi/{$id}", ['tanggal_update' => '2026-11-27'], $h)->assertStatus(422);
        $this->putJson("/api/admin/pasca-adopsi/{$id}", ['status_kondisi' => 'Bahagia'], $h)->assertStatus(422);
        $this->putJson("/api/admin/pasca-adopsi/{$id}", ['status_kondisi' => 'Sakit', 'catatan' => 'Flu'], $h)->assertOk();

        // serah terima pemilik catatan tidak bisa dipindahkan
        $lain = $this->adopsi('2026-11-10');
        $this->putJson("/api/admin/pasca-adopsi/{$id}", ['id_serah_terima' => $lain->id_serah_terima, 'catatan' => 'x'], $h)->assertOk();
        $this->assertSame($st->id_serah_terima, PascaAdopsi::find($id)->id_serah_terima);
        $this->putJson('/api/admin/pasca-adopsi/99999', ['catatan' => 'x'], $h)->assertStatus(404);
    }

    public function test_hapus_catatan_menghitung_ulang_kondisi_terkini_dan_diaudit(): void
    {
        $st = $this->adopsi('2026-11-01', [['2026-11-10', 'Sehat', null], ['2026-11-20', 'Sakit', 'Flu']]);
        $terbaru = PascaAdopsi::where('status_kondisi', 'Sakit')->first();
        $h = $this->asRole('admin');

        $this->deleteJson("/api/admin/pasca-adopsi/{$terbaru->id_pasca_adopsi}", [], $h)->assertOk();

        $this->getJson("/api/admin/pasca-adopsi/{$st->id_serah_terima}/riwayat", $h)
            ->assertJsonPath('data.pemantauan.kondisi_terkini', 'Sehat')
            ->assertJsonPath('data.pemantauan.jumlah_update', 1)
            ->assertJsonPath('data.pemantauan.jatuh_tempo', '2026-11-17');
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'DELETE', 'nama_tabel' => 'pasca_adopsi', 'id_data' => $terbaru->id_pasca_adopsi]);
        $this->deleteJson('/api/admin/pasca-adopsi/99999', [], $h)->assertStatus(404);
    }

    public function test_menghapus_serah_terima_beserta_riwayatnya_tidak_diizinkan_diam_diam(): void
    {
        // pasca_adopsi bertaut CASCADE ke serah_terima; tidak ada endpoint yang menghapus serah terima,
        // jadi riwayat kondisi tidak bisa hilang lewat API.
        $st = $this->adopsi('2026-11-01', [['2026-11-10', 'Sehat', null]]);

        $this->deleteJson("/api/admin/serah-terima/{$st->id_serah_terima}", [], $this->asRole('admin'))->assertStatus(405);
        $this->assertDatabaseCount('pasca_adopsi', 1);
    }
}

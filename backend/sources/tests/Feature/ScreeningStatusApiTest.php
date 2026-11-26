<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\PengajuanAdopsi;
use App\Models\ScreeningAdopsi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/** dev-backend-08: screening, mesin status pengajuan, sinkron status kucing, dan notifikasi. */
class ScreeningStatusApiTest extends TestCase
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

    private function hasil(array $o = []): array
    {
        return $o + [
            'kelayakan_hunian' => 'Rumah indoor, berjaring', 'kestabilan_ekonomi' => 'Karyawan tetap',
            'pemahaman_perawatan' => 'Paham vaksin dan steril', 'sikap_proklinik' => 'Bersedia ke dokter hewan',
            'hasil_screening' => 'Lolos',
        ];
    }

    private function ubahStatus(PengajuanAdopsi $p, string $ke, array $h)
    {
        return $this->putJson("/api/admin/adopsi/{$p->id_pengajuan}/status", ['status' => $ke], $h);
    }

    private function screening(PengajuanAdopsi $p, array $data, array $h)
    {
        return $this->postJson("/api/admin/adopsi/{$p->id_pengajuan}/screening", $data, $h);
    }

    // ------------------------------------------------------------- screening

    public function test_hasil_screening_hanya_bisa_diisi_saat_status_screening(): void
    {
        $h = $this->asRole('admin');

        foreach (['MENUNGGU', 'DISETUJUI', 'DITOLAK', 'SELESAI'] as $status) {
            $p = $this->buatPengajuan(null, $status);
            $this->screening($p, $this->hasil(), $h)->assertStatus(422)->assertJsonValidationErrors('status');
        }
        $this->assertDatabaseCount('screening_adopsi', 0);
    }

    public function test_simpan_hasil_mencatat_admin_penanggung_jawab_dan_tanggal_bawaan_wib(): void
    {
        $p = $this->buatPengajuan(null, 'SCREENING');
        $admin = $this->makeUser('admin', 'aktif', 'petugas1@uji.test');
        $h = $this->bearer($this->tokenFor($admin));

        $res = $this->screening($p, $this->hasil(), $h)->assertCreated()
            ->assertJsonPath('data.hasil_screening', 'Lolos')
            ->assertJsonPath('data.kelayakan_hunian', 'Rumah indoor, berjaring')
            ->assertJsonPath('data.tanggal_screening', Carbon::now('Asia/Jakarta')->toDateString())
            ->assertJsonPath('data.petugas.id_user', $admin->id_user);

        $this->assertDatabaseHas('screening_adopsi', ['id_pengajuan' => $p->id_pengajuan, 'id_user' => $admin->id_user]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'screening_adopsi', 'id_data' => $res->json('data.id_screening')]);
    }

    public function test_menyimpan_lagi_memperbarui_hasil_yang_sama_dan_petugas_menjadi_penyimpan_terakhir(): void
    {
        $p = $this->buatPengajuan(null, 'SCREENING');
        $satu = $this->makeUser('admin', 'aktif', 'satu@uji.test');
        $dua = $this->makeUser('admin', 'aktif', 'dua@uji.test');

        $this->screening($p, $this->hasil(['tanggal_screening' => '2026-11-05']), $this->bearer($this->tokenFor($satu)))->assertCreated();
        $this->screening($p, $this->hasil(['hasil_screening' => 'Ragu', 'kestabilan_ekonomi' => 'Freelance, belum tentu stabil']), $this->bearer($this->tokenFor($dua)))
            ->assertOk()                                                // 200, bukan 201: memperbarui
            ->assertJsonPath('data.hasil_screening', 'Ragu')
            ->assertJsonPath('data.petugas.id_user', $dua->id_user)
            ->assertJsonPath('data.tanggal_screening', '2026-11-05');   // tanggal awal dipertahankan

        $this->assertSame(1, ScreeningAdopsi::where('id_pengajuan', $p->id_pengajuan)->count());
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'screening_adopsi']);
    }

    public function test_validasi_screening(): void
    {
        $p = $this->buatPengajuan(null, 'SCREENING');
        $h = $this->asRole('admin');
        $besok = Carbon::now('Asia/Jakarta')->addDay()->toDateString();

        $this->screening($p, [], $h)->assertStatus(422)->assertJsonValidationErrors('hasil_screening');
        foreach ([
            ['hasil_screening' => 'Mungkin'],
            ['tanggal_screening' => $besok],
            ['tanggal_screening' => '12-11-2026'],
            ['kelayakan_hunian' => str_repeat('a', 2001)],
        ] as $salah) {
            $this->screening($p, $this->hasil($salah), $h)->assertStatus(422);
        }
        $this->assertDatabaseCount('screening_adopsi', 0);
        $this->assertSame(0, AuditLog::where('nama_tabel', 'screening_adopsi')->count());
    }

    public function test_hasil_lolos_mengharuskan_keempat_kriteria_terisi(): void
    {
        $p = $this->buatPengajuan(null, 'SCREENING');
        $h = $this->asRole('admin');

        $this->screening($p, ['hasil_screening' => 'Lolos', 'kelayakan_hunian' => 'Baik'], $h)->assertStatus(422)
            ->assertJsonValidationErrors(['kestabilan_ekonomi', 'pemahaman_perawatan', 'sikap_proklinik']);
        $this->screening($p, $this->hasil(['sikap_proklinik' => '   ']), $h)->assertStatus(422)->assertJsonValidationErrors('sikap_proklinik');

        // Ragu / Tidak Lolos boleh dengan kriteria sebagian
        $this->screening($p, ['hasil_screening' => 'Ragu', 'kelayakan_hunian' => 'Kos, belum izin pemilik'], $h)->assertCreated();
        $this->screening($p, ['hasil_screening' => 'Tidak Lolos'], $h)->assertOk();
    }

    public function test_screening_pengajuan_tidak_ada_404_walau_isian_salah(): void
    {
        $this->postJson('/api/admin/adopsi/99999/screening', [], $this->asRole('admin'))->assertStatus(404);
    }

    // ----------------------------------------------------------- mesin status

    public function test_mulai_pemeriksaan_menahan_kucing_dan_membuat_notifikasi_serta_audit(): void
    {
        $p = $this->buatPengajuan();

        $this->ubahStatus($p, 'SCREENING', $this->asRole('admin'))->assertOk()
            ->assertJsonPath('data.status_pengajuan', 'SCREENING')
            ->assertJsonPath('data.kucing.status_kucing', 'Proses Screening');

        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);
        $this->assertSame(['Email', 'WhatsApp'], Notifikasi::where('id_pengajuan', $p->id_pengajuan)->orderBy('kanal')->pluck('kanal')->all());
        $this->assertSame(2, Notifikasi::where('jenis_notifikasi', 'Status Adopsi')->where('status_pengiriman', 'PENDING')->whereNull('id_user')->count());
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UBAH_STATUS', 'nama_tabel' => 'pengajuan_adopsi', 'id_data' => $p->id_pengajuan]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'SINKRON_STATUS_KUCING', 'nama_tabel' => 'kucing', 'id_data' => $this->kucing->id_kucing]);
    }

    public function test_perpindahan_status_yang_tidak_diizinkan_ditolak(): void
    {
        $h = $this->asRole('admin');
        $menunggu = $this->buatPengajuan();
        $ditolak = $this->buatPengajuan($this->buatKucing(['status_kucing' => 'Tersedia']), 'DITOLAK');

        $this->ubahStatus($menunggu, 'DISETUJUI', $h)->assertStatus(422)->assertJsonValidationErrors('status');   // lompat tahap
        $this->ubahStatus($menunggu, 'MENUNGGU', $h)->assertStatus(422);                                          // bukan nilai yang boleh dipilih
        $this->ubahStatus($menunggu, 'SELESAI', $h)->assertStatus(422);                                           // hanya oleh serah terima
        $this->ubahStatus($menunggu, 'APA-SAJA', $h)->assertStatus(422);
        $this->putJson("/api/admin/adopsi/{$menunggu->id_pengajuan}/status", [], $h)->assertStatus(422);
        foreach (['SCREENING', 'DISETUJUI', 'DITOLAK'] as $ke) {
            $this->ubahStatus($ditolak, $ke, $h)->assertStatus(422);                                              // DITOLAK final
        }
        $this->ubahStatus($this->buatPengajuan($this->buatKucing(['status_kucing' => 'Tersedia']), 'SCREENING'), 'SCREENING', $h)->assertStatus(422);   // status sama

        $this->assertSame('MENUNGGU', $menunggu->fresh()->status_pengajuan);
        $this->assertSame(0, Notifikasi::count());
    }

    public function test_status_tidak_ada_404_walau_isian_salah(): void
    {
        $this->putJson('/api/admin/adopsi/99999/status', [], $this->asRole('admin'))->assertStatus(404);
    }

    public function test_menyetujui_butuh_hasil_screening_dan_bukan_tidak_lolos(): void
    {
        $p = $this->buatPengajuan(null, 'SCREENING');
        $this->kucing->update(['status_kucing' => 'Proses Screening']);
        $h = $this->asRole('admin');

        $this->ubahStatus($p, 'DISETUJUI', $h)->assertStatus(422)->assertJsonValidationErrors('status');       // belum ada hasil
        $s = $this->buatScreening($p, 'Tidak Lolos');
        $this->ubahStatus($p, 'DISETUJUI', $h)->assertStatus(422);                                              // hasil Tidak Lolos
        $this->assertSame('SCREENING', $p->fresh()->status_pengajuan);

        $s->update(['hasil_screening' => 'Ragu']);
        $this->ubahStatus($p, 'DISETUJUI', $h)->assertOk()->assertJsonPath('data.status_pengajuan', 'DISETUJUI');
        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);                       // kucing tetap ditahan sampai serah terima
    }

    public function test_menolak_setelah_memegang_kucing_mengembalikan_kucing_menjadi_tersedia(): void
    {
        $h = $this->asRole('admin');

        // dari SCREENING
        $a = $this->buatPengajuan(null, 'SCREENING');
        $this->kucing->update(['status_kucing' => 'Proses Screening']);
        $this->ubahStatus($a, 'DITOLAK', $h)->assertOk();
        $this->assertSame('Tersedia', $this->kucing->fresh()->status_kucing);

        // dari DISETUJUI (pembatalan)
        $b = $this->buatPengajuan(null, 'DISETUJUI');
        $this->kucing->update(['status_kucing' => 'Proses Screening']);
        $this->ubahStatus($b, 'DITOLAK', $h)->assertOk();
        $this->assertSame('Tersedia', $this->kucing->fresh()->status_kucing);
    }

    public function test_menolak_pengajuan_yang_belum_diperiksa_tidak_mengubah_status_kucing(): void
    {
        $p = $this->buatPengajuan();

        $this->ubahStatus($p, 'DITOLAK', $this->asRole('admin'))->assertOk();

        $this->assertSame('Tersedia', $this->kucing->fresh()->status_kucing);
        $this->assertSame(0, AuditLog::where('aktivitas', 'SINKRON_STATUS_KUCING')->count());
        $this->assertSame(2, Notifikasi::where('id_pengajuan', $p->id_pengajuan)->count());   // pemohon tetap diberi tahu
    }

    public function test_pengajuan_lain_untuk_kucing_yang_sama_tidak_bisa_diperiksa_selama_kucing_dipegang(): void
    {
        $h = $this->asRole('admin');
        $a = $this->buatPengajuan();
        $b = $this->buatPengajuan();   // pelamar lain, kucing sama (sudah mengantre sebelum kucing dipegang)

        $this->ubahStatus($a, 'SCREENING', $h)->assertOk();
        $this->ubahStatus($b, 'SCREENING', $h)->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame('MENUNGGU', $b->fresh()->status_pengajuan);

        // setelah A ditolak, kucing bebas dan B bisa diperiksa
        $this->ubahStatus($a, 'DITOLAK', $h)->assertOk();
        $this->ubahStatus($b, 'SCREENING', $h)->assertOk();
        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);
    }

    public function test_pemeriksaan_ditolak_bila_kucing_sudah_tidak_tersedia(): void
    {
        $h = $this->asRole('admin');

        foreach (['Hilang', 'Almarhum', 'Diadopsi', 'Terdata'] as $status) {
            $kucing = $this->buatKucing(['status_kucing' => $status]);
            $p = $this->buatPengajuan($kucing);
            $this->ubahStatus($p, 'SCREENING', $h)->assertStatus(422)->assertJsonValidationErrors('status');
            $this->assertSame($status, $kucing->fresh()->status_kucing);
        }
    }

    public function test_persetujuan_bisa_dikoreksi_kembali_ke_screening_lalu_hasil_dapat_diubah(): void
    {
        $p = $this->buatPengajuan(null, 'DISETUJUI');
        $this->kucing->update(['status_kucing' => 'Proses Screening']);
        $this->buatScreening($p, 'Lolos');
        $h = $this->asRole('admin');

        $this->screening($p, $this->hasil(), $h)->assertStatus(422);                        // terkunci selagi DISETUJUI
        $this->ubahStatus($p, 'SCREENING', $h)->assertOk();
        $this->screening($p, $this->hasil(['hasil_screening' => 'Ragu']), $h)->assertOk();    // kini bisa diubah
        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);
    }

    // ----------------------------------------------------------- notifikasi

    public function test_konfirmasi_saat_mengajukan_membuat_antrean_whatsapp_dan_email_berisi_kode_tracking(): void
    {
        $this->postJson('/api/adopsi/ajukan', [
            'id_kucing' => $this->kucing->id_kucing, 'nama_pemohon' => 'Dewi Anggraini', 'nomor_whatsapp' => '081234567890',
            'email' => 'dewi@contoh.id', 'alamat' => 'Jl. Ketintang Baru No. 12, Surabaya', 'persetujuan_data' => true,
        ])->assertCreated();
        $p = PengajuanAdopsi::first();

        $notif = Notifikasi::where('id_pengajuan', $p->id_pengajuan)->orderBy('kanal')->get();
        $this->assertSame(['Email', 'WhatsApp'], $notif->pluck('kanal')->all());
        foreach ($notif as $n) {
            $this->assertSame('Konfirmasi Pengajuan', $n->jenis_notifikasi);
            $this->assertSame('PENDING', $n->status_pengiriman);
            $this->assertNull($n->id_user);
            $this->assertNull($n->tanggal_dikirim);
            $this->assertStringContainsString($p->kode_tracking, $n->isi_pesan);
            $this->assertStringContainsString($p->nomor_pengajuan, $n->isi_pesan);
            $this->assertStringContainsString('Dewi', $n->isi_pesan);
            $this->assertStringContainsString(rtrim(config('app.frontend_url'), '/').'/adopsi/tracking/'.$p->kode_tracking, $n->isi_pesan);
        }
    }

    public function test_pesan_notifikasi_status_tidak_membocorkan_alasan_atau_data_pribadi(): void
    {
        $p = $this->buatPengajuan(null, 'SCREENING', ['nama_pemohon' => 'Rahmat Hidayat']);
        $this->kucing->update(['status_kucing' => 'Proses Screening']);
        $this->buatScreening($p, 'Tidak Lolos')->update(['kelayakan_hunian' => 'RAHASIA-ALASAN-INTERNAL']);

        $this->ubahStatus($p, 'DITOLAK', $this->asRole('admin'))->assertOk();

        foreach (Notifikasi::where('id_pengajuan', $p->id_pengajuan)->get() as $n) {
            $this->assertSame('Status Adopsi', $n->jenis_notifikasi);
            $this->assertStringContainsString('Tidak Disetujui', $n->isi_pesan);
            $this->assertStringContainsString('Rahmat', $n->isi_pesan);
            foreach (['RAHASIA-ALASAN-INTERNAL', 'Hidayat', $p->email, $p->nomor_whatsapp, 'Jl. Contoh', 'Tidak Lolos'] as $rahasia) {
                $this->assertStringNotContainsString($rahasia, $n->isi_pesan, "Notifikasi membocorkan: {$rahasia}");
            }
        }
    }

    // ------------------------------------------------- konsistensi data kucing

    public function test_status_kucing_yang_sedang_dipegang_pengajuan_tidak_bisa_diubah_dari_edit_kucing(): void
    {
        $p = $this->buatPengajuan(null, 'SCREENING');
        $this->kucing->update(['status_kucing' => 'Proses Screening']);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}", ['status_kucing' => 'Terdata'], $h)
            ->assertStatus(422)->assertJsonValidationErrors('status_kucing');
        $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}", ['status_kucing' => 'Hilang'], $h)->assertStatus(422);
        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);

        // mengubah data lain atau mengirim ulang status yang sama tetap boleh
        $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}", ['deskripsi' => 'Ciri baru', 'status_kucing' => 'Proses Screening'], $h)->assertOk();

        // setelah pengajuan ditolak, kucing kembali Tersedia dan bebas diubah
        $this->ubahStatus($p, 'DITOLAK', $h)->assertOk();
        $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}", ['status_kucing' => 'Terdata'], $h)->assertOk();
    }

    public function test_kucing_proses_screening_tanpa_pengajuan_yang_memegang_boleh_dikeluarkan_dari_status_itu(): void
    {
        $this->kucing->update(['status_kucing' => 'Proses Screening']);   // sisa data lama, tidak ada pengajuan aktif

        $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}", ['status_kucing' => 'Terdata'], $this->asRole('admin'))->assertOk();
    }

    // -------------------------------------------------------- alur lengkap

    public function test_alur_lengkap_dari_pengajuan_publik_sampai_disetujui_terlihat_di_pelacakan(): void
    {
        $kode = $this->postJson('/api/adopsi/ajukan', [
            'id_kucing' => $this->kucing->id_kucing, 'nama_pemohon' => 'Dewi Anggraini', 'nomor_whatsapp' => '081234567890',
            'email' => 'dewi@contoh.id', 'alamat' => 'Jl. Ketintang Baru No. 12, Surabaya', 'persetujuan_data' => true,
        ])->assertCreated()->json('data.kode_tracking');
        $p = PengajuanAdopsi::first();
        $h = $this->asRole('admin');

        $this->getJson("/api/adopsi/tracking/{$kode}")->assertOk()->assertJsonPath('data.status', 'MENUNGGU');
        $this->ubahStatus($p, 'SCREENING', $h)->assertOk();
        $this->getJson("/api/adopsi/tracking/{$kode}")->assertJsonPath('data.status', 'SCREENING');

        $this->screening($p, $this->hasil(['kelayakan_hunian' => 'CATATAN-INTERNAL-RUMAH']), $h)->assertCreated();
        $this->ubahStatus($p, 'DISETUJUI', $h)->assertOk();

        $publik = $this->getJson("/api/adopsi/tracking/{$kode}")->assertOk()
            ->assertJsonPath('data.status', 'DISETUJUI')
            ->assertJsonPath('data.status_label', 'Disetujui');
        $this->assertStringNotContainsString('CATATAN-INTERNAL-RUMAH', json_encode($publik->json()));   // hasil screening tak pernah publik (BR-08)
        $this->assertStringNotContainsString('Lolos', json_encode($publik->json()));

        // 1 konfirmasi + 2 perubahan status, masing-masing dua kanal
        $this->assertSame(6, Notifikasi::where('id_pengajuan', $p->id_pengajuan)->count());
        $this->assertSame('Proses Screening', $this->kucing->fresh()->status_kucing);

        // kucing yang sedang diproses hilang dari katalog publik
        $this->getJson("/api/kucing/{$this->kucing->id_kucing}")->assertStatus(404);
    }
}

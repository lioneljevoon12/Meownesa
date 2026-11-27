<?php

namespace Tests\Feature;

use App\Mail\NotifikasiMail;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\PascaAdopsi;
use App\Models\PengajuanAdopsi;
use App\Models\SerahTerima;
use App\Services\NotifikasiPengajuanService;
use App\Services\WhatsAppNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\Concerns\MembuatPengajuan;
use Tests\TestCase;

/**
 * dev-backend-11: notifikasi email, WhatsApp manual (wa.me), dan pengingat kabar kondisi kucing
 * (MEOW-F-30 s/d F-32, BR-12). Waktu dibekukan pada 26 Nov 2026 10.00 WIB.
 */
class NotifikasiApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, MembuatPengajuan, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
        Carbon::setTestNow(Carbon::create(2026, 11, 26, 10, 0, 0, 'Asia/Jakarta'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pengajuanBaru(array $o = []): PengajuanAdopsi
    {
        return $this->buatPengajuan($this->buatKucing(['status_kucing' => 'Tersedia']), 'MENUNGGU', $o);
    }

    /** Adopsi yang sudah diserahterimakan: [[tanggal, kondisi, catatan?], ...]. */
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

    private function notif(PengajuanAdopsi $p, string $kanal, string $pesan = 'Halo, ini pesan uji.', array $o = []): Notifikasi
    {
        return Notifikasi::create($o + [
            'id_pengajuan' => $p->id_pengajuan, 'jenis_notifikasi' => 'Status Adopsi', 'kanal' => $kanal,
            'isi_pesan' => $pesan, 'status_pengiriman' => 'PENDING',
        ]);
    }

    // ------------------------------------------------------------ hak akses

    public function test_semua_endpoint_notifikasi_hanya_untuk_admin(): void
    {
        $n = $this->notif($this->pengajuanBaru(), 'Email');
        $rute = [
            ['getJson', '/api/admin/notifikasi'],
            ['postJson', '/api/admin/notifikasi/kirim'],
            ['postJson', '/api/admin/notifikasi/pengingat'],
            ['putJson', "/api/admin/notifikasi/{$n->id_notifikasi}/status"],
        ];
        $panggil = fn (string $m, string $u, array $h = []) => $m === 'getJson' ? $this->getJson($u, $h) : $this->$m($u, [], $h);

        foreach ($rute as [$metode, $uri]) {
            $this->app['auth']->forgetGuards();
            $panggil($metode, $uri)->assertStatus(401);
            $panggil($metode, $uri, $this->asRole('superadmin'))->assertStatus(403);
            $panggil($metode, $uri, $this->asRole('adopter'))->assertStatus(403);
        }

        Mail::assertNothingSent();
        $this->assertSame('PENDING', $n->refresh()->status_pengiriman);
    }

    // ------------------------------------------------------------ email

    public function test_kirim_tanpa_id_mengirim_semua_email_pending_dan_membiarkan_whatsapp(): void
    {
        $p1 = $this->pengajuanBaru();
        $p2 = $this->pengajuanBaru();
        $e1 = $this->notif($p1, 'Email', 'Pesan satu');
        $e2 = $this->notif($p2, 'Email', 'Pesan dua');
        $wa = $this->notif($p1, 'WhatsApp', 'Pesan WA');
        $sudah = $this->notif($p1, 'Email', 'Sudah lama', ['status_pengiriman' => 'TERKIRIM']);
        $gagal = $this->notif($p1, 'Email', 'Pernah gagal', ['status_pengiriman' => 'GAGAL']);

        $this->postJson('/api/admin/notifikasi/kirim', [], $this->asRole('admin'))->assertOk()
            ->assertJsonPath('data.diproses', 2)->assertJsonPath('data.terkirim', 2)->assertJsonPath('data.gagal', 0);

        Mail::assertSent(NotifikasiMail::class, 2);
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo($p1->email) && $m->isi === 'Pesan satu' && $m->jenis === 'Status Adopsi');
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo($p2->email));

        foreach ([$e1, $e2] as $e) {
            $e->refresh();
            $this->assertSame('TERKIRIM', $e->status_pengiriman);
            $this->assertSame('2026-11-26', $e->tanggal_dikirim->format('Y-m-d'));
            $this->assertSame(1, $e->percobaan);
            $this->assertNull($e->catatan_galat);
        }
        $this->assertSame('PENDING', $wa->refresh()->status_pengiriman);     // WhatsApp tidak pernah dikirim sistem
        $this->assertSame('GAGAL', $gagal->refresh()->status_pengiriman);    // yang gagal hanya dicoba ulang atas permintaan
    }

    public function test_kirim_tanpa_antrean_memberi_pesan_yang_jelas(): void
    {
        $this->postJson('/api/admin/notifikasi/kirim', [], $this->asRole('admin'))->assertOk()
            ->assertJsonPath('message', 'Tidak ada email yang menunggu dikirim.')->assertJsonPath('data.diproses', 0);
        Mail::assertNothingSent();
    }

    public function test_token_aktivasi_tersamarkan_setelah_email_terkirim(): void
    {
        $p = $this->pengajuanBaru();
        $tautan = 'http://localhost:3000/aktivasi-akun?email=pemohon%40contoh.id&token=abc123DEF456';
        app(NotifikasiPengajuanService::class)->serahTerima($p, $tautan);
        $email = Notifikasi::where('kanal', 'Email')->firstOrFail();

        $this->postJson('/api/admin/notifikasi/kirim', ['id_notifikasi' => $email->id_notifikasi], $this->asRole('admin'))->assertOk()
            ->assertJsonPath('data.status_pengiriman', 'TERKIRIM');

        // Email sungguhan memuat tautan utuh, tetapi salinan di basis data tidak lagi menyimpan tokennya.
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => str_contains($m->isi, 'token=abc123DEF456'));
        $this->assertStringNotContainsString('abc123DEF456', $email->refresh()->isi_pesan);
        $this->assertStringContainsString('token=[disamarkan]', $email->isi_pesan);

        // Salinan WhatsApp belum terkirim, jadi tautan aktivasinya masih utuh untuk admin.
        $this->assertStringContainsString('abc123DEF456', Notifikasi::where('kanal', 'WhatsApp')->firstOrFail()->isi_pesan);
    }

    public function test_kegagalan_kirim_dicatat_lalu_bisa_dicoba_ulang(): void
    {
        $p = $this->pengajuanBaru();
        $n = $this->notif($p, 'Email', 'Aktivasi: http://x.test/aktivasi-akun?token=rahasia999');
        $h = $this->asRole('admin');

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Connection refused'));

        $this->postJson('/api/admin/notifikasi/kirim', ['id_notifikasi' => $n->id_notifikasi], $h)->assertOk()
            ->assertJsonPath('data.status_pengiriman', 'GAGAL')
            ->assertJsonPath('data.catatan_galat', 'Connection refused')
            ->assertJsonPath('data.percobaan', 1);
        $this->assertNull($n->refresh()->tanggal_dikirim);
        $this->assertStringContainsString('rahasia999', $n->isi_pesan);   // belum sampai, token masih dibutuhkan

        // koneksi pulih: buang mock lalu pasang fake baru
        $this->app->forgetInstance('mail.manager');
        Mail::clearResolvedInstance('mail.manager');
        Mail::fake();
        $this->postJson('/api/admin/notifikasi/kirim', ['id_notifikasi' => $n->id_notifikasi], $h)->assertOk()
            ->assertJsonPath('data.status_pengiriman', 'TERKIRIM')->assertJsonPath('data.percobaan', 2)->assertJsonPath('data.catatan_galat', null);
        Mail::assertSent(NotifikasiMail::class, 1);
    }

    public function test_email_tanpa_penerima_ditandai_gagal(): void
    {
        $p = $this->pengajuanBaru();
        $n = $this->notif($p, 'Email');
        $n->update(['id_pengajuan' => null]);

        $this->postJson('/api/admin/notifikasi/kirim', [], $this->asRole('admin'))->assertOk()->assertJsonPath('data.gagal', 1);

        $this->assertSame('GAGAL', $n->refresh()->status_pengiriman);
        $this->assertSame('Alamat email penerima tidak tersedia.', $n->catatan_galat);
        Mail::assertNothingSent();
    }

    public function test_kirim_satu_notifikasi_menolak_yang_tidak_sesuai(): void
    {
        $p = $this->pengajuanBaru();
        $wa = $this->notif($p, 'WhatsApp');
        $sudah = $this->notif($p, 'Email', 'x', ['status_pengiriman' => 'TERKIRIM']);
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/notifikasi/kirim', ['id_notifikasi' => $wa->id_notifikasi], $h)->assertStatus(422)->assertJsonPath('success', false);
        $this->postJson('/api/admin/notifikasi/kirim', ['id_notifikasi' => $sudah->id_notifikasi], $h)->assertStatus(422)->assertJsonPath('message', 'Email ini sudah terkirim.');
        $this->postJson('/api/admin/notifikasi/kirim', ['id_notifikasi' => 99999], $h)->assertNotFound();
        $this->postJson('/api/admin/notifikasi/kirim', ['id_notifikasi' => 'abc'], $h)->assertStatus(422)->assertJsonValidationErrors('id_notifikasi');

        Mail::assertNothingSent();
    }

    public function test_konfirmasi_pengajuan_terkirim_lewat_email_dengan_kode_tracking(): void
    {
        $p = $this->pengajuanBaru();
        app(NotifikasiPengajuanService::class)->konfirmasi($p);

        $this->artisan('notifikasi:kirim')->assertSuccessful();

        Mail::assertSent(NotifikasiMail::class, 1);
        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo($p->email)
            && str_contains($m->isi, $p->kode_tracking) && str_contains($m->isi, $p->nomor_pengajuan));
        $this->assertSame(1, Notifikasi::where('kanal', 'WhatsApp')->where('status_pengiriman', 'PENDING')->count());
    }

    // ------------------------------------------------------------ WhatsApp manual

    public function test_histori_memuat_tautan_wa_me_untuk_pesan_whatsapp_yang_belum_terkirim(): void
    {
        $p = $this->pengajuanBaru(['nomor_whatsapp' => '6281234567890']);
        $pesan = "Halo Budi, status pengajuan ADP-1 kini: Disetujui & siap!\nSampai jumpa.";
        $wa = $this->notif($p, 'WhatsApp', $pesan);
        $this->notif($p, 'Email', $pesan);
        $this->notif($p, 'WhatsApp', 'Sudah lama', ['status_pengiriman' => 'TERKIRIM']);

        $res = $this->getJson('/api/admin/notifikasi', $this->asRole('admin'))->assertOk();
        $baris = collect($res->json('data'))->keyBy(fn ($r) => $r['kanal'].'|'.$r['status_pengiriman']);

        $tautan = $baris['WhatsApp|PENDING']['tautan_whatsapp'];
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $tautan);
        $this->assertSame($pesan, rawurldecode(substr($tautan, strlen('https://wa.me/6281234567890?text='))));
        $this->assertNull($baris['Email|PENDING']['tautan_whatsapp']);
        $this->assertNull($baris['WhatsApp|TERKIRIM']['tautan_whatsapp']);
        $this->assertSame('6281234567890', $baris['WhatsApp|PENDING']['penerima']['nomor_whatsapp']);
        $this->assertSame($wa->id_notifikasi, $baris['WhatsApp|PENDING']['id_notifikasi']);
    }

    public function test_nomor_whatsapp_dinormalkan_ke_format_internasional(): void
    {
        $wa = new WhatsAppNotificationService;

        $this->assertSame('6281234567890', $wa->nomorInternasional('081234567890'));
        $this->assertSame('6281234567890', $wa->nomorInternasional('+62 812-3456-7890'));
        $this->assertSame('6281234567890', $wa->nomorInternasional('6281234567890'));
        $this->assertNull($wa->nomorInternasional('12345'));
        $this->assertNull($wa->nomorInternasional(''));
        $this->assertNull($wa->nomorInternasional('0211234567'));   // telepon rumah, bukan WhatsApp seluler
    }

    public function test_admin_menandai_whatsapp_terkirim_dan_token_tersamarkan(): void
    {
        $p = $this->pengajuanBaru();
        $wa = $this->notif($p, 'WhatsApp', 'Atur password: http://x.test/aktivasi-akun?email=a%40b.id&token=tokenrahasia777 (7 hari).');
        $h = $this->asRole('admin');

        $res = $this->putJson("/api/admin/notifikasi/{$wa->id_notifikasi}/status", ['status' => 'TERKIRIM'], $h)->assertOk()
            ->assertJsonPath('data.status_pengiriman', 'TERKIRIM')
            ->assertJsonPath('data.tanggal_dikirim', '2026-11-26')
            ->assertJsonPath('data.tautan_whatsapp', null);

        $this->assertStringNotContainsString('tokenrahasia777', $res->json('data.isi_pesan'));
        $this->assertStringContainsString('token=[disamarkan]', $wa->refresh()->isi_pesan);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'notifikasi', 'id_data' => $wa->id_notifikasi]);

        // sudah terkirim: tidak dapat ditandai ulang
        $this->putJson("/api/admin/notifikasi/{$wa->id_notifikasi}/status", ['status' => 'GAGAL'], $h)->assertStatus(422);
    }

    public function test_admin_menandai_whatsapp_gagal_dengan_catatan_lalu_bisa_ditandai_terkirim(): void
    {
        $p = $this->pengajuanBaru();
        $wa = $this->notif($p, 'WhatsApp');
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/notifikasi/{$wa->id_notifikasi}/status", ['status' => 'GAGAL', 'catatan' => '  Nomor tidak terdaftar di WhatsApp  '], $h)->assertOk()
            ->assertJsonPath('data.status_pengiriman', 'GAGAL')->assertJsonPath('data.catatan_galat', 'Nomor tidak terdaftar di WhatsApp')
            ->assertJsonPath('data.tautan_whatsapp', fn ($v) => str_starts_with((string) $v, 'https://wa.me/'));

        $this->putJson("/api/admin/notifikasi/{$wa->id_notifikasi}/status", ['status' => 'TERKIRIM'], $h)->assertOk()
            ->assertJsonPath('data.status_pengiriman', 'TERKIRIM')->assertJsonPath('data.percobaan', 2)->assertJsonPath('data.catatan_galat', null);
    }

    public function test_tandai_status_menolak_email_dan_input_salah(): void
    {
        $p = $this->pengajuanBaru();
        $email = $this->notif($p, 'Email');
        $wa = $this->notif($p, 'WhatsApp');
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/notifikasi/{$email->id_notifikasi}/status", ['status' => 'TERKIRIM'], $h)->assertStatus(422)->assertJsonPath('success', false);
        $this->putJson("/api/admin/notifikasi/{$wa->id_notifikasi}/status", [], $h)->assertStatus(422)->assertJsonValidationErrors('status');
        $this->putJson("/api/admin/notifikasi/{$wa->id_notifikasi}/status", ['status' => 'PENDING'], $h)->assertStatus(422)->assertJsonValidationErrors('status');
        $this->putJson("/api/admin/notifikasi/{$wa->id_notifikasi}/status", ['status' => 'GAGAL', 'catatan' => str_repeat('a', 256)], $h)->assertStatus(422)->assertJsonValidationErrors('catatan');
        $this->putJson('/api/admin/notifikasi/99999/status', ['status' => 'TERKIRIM'], $h)->assertNotFound();

        $this->assertSame('PENDING', $email->refresh()->status_pengiriman);
        $this->assertSame('PENDING', $wa->refresh()->status_pengiriman);
    }

    // ------------------------------------------------------------ histori

    public function test_histori_terbaru_di_atas_dengan_filter_dan_jumlah_per_status(): void
    {
        $a = $this->pengajuanBaru();
        $b = $this->pengajuanBaru();
        $this->notif($a, 'Email', 'a1', ['status_pengiriman' => 'TERKIRIM']);
        $this->notif($a, 'WhatsApp', 'a2');
        $this->notif($b, 'Email', 'b1', ['status_pengiriman' => 'GAGAL', 'jenis_notifikasi' => 'Reminder Monitoring']);
        $this->notif($b, 'WhatsApp', 'b2');
        $h = $this->asRole('admin');

        $semua = $this->getJson('/api/admin/notifikasi', $h)->assertOk()
            ->assertJsonPath('meta.total', 4)->assertJsonPath('meta.jumlah_per_status', ['PENDING' => 2, 'TERKIRIM' => 1, 'GAGAL' => 1]);
        $this->assertSame(['b2', 'b1', 'a2', 'a1'], array_column($semua->json('data'), 'isi_pesan'));

        $this->getJson('/api/admin/notifikasi?status=PENDING', $h)->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.jumlah_per_status.GAGAL', 1);                       // jumlah per status tidak ikut filter status
        $this->getJson('/api/admin/notifikasi?kanal=Email', $h)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.jumlah_per_status.PENDING', 0);
        $this->getJson('/api/admin/notifikasi?jenis=Reminder%20Monitoring', $h)->assertJsonPath('meta.total', 1);
        $this->getJson("/api/admin/notifikasi?id_pengajuan={$a->id_pengajuan}", $h)->assertJsonPath('meta.total', 2);
        $this->getJson('/api/admin/notifikasi?per_page=3&page=2', $h)->assertJsonPath('meta.halaman', 2)->assertJsonPath('meta.halaman_akhir', 2)->assertJsonCount(1, 'data');

        foreach (['status=Selesai', 'kanal=SMS', 'per_page=0', 'per_page=101', 'page=0', 'id_pengajuan=x'] as $salah) {
            $this->getJson("/api/admin/notifikasi?{$salah}", $h)->assertStatus(422);
        }
    }

    // ------------------------------------------------------------ pengingat

    public function test_pengingat_dibuat_hanya_untuk_adopsi_yang_sudah_jatuh_tempo(): void
    {
        $terlambat = $this->adopsi('2026-11-10', [], ['nama_pemohon' => 'Siti Aminah']);                  // jatuh tempo 17 Nov: terlambat 9 hari
        $hariIni = $this->adopsi('2026-11-19', [['2026-11-19', 'Sehat', null]]);                          // jatuh tempo 26 Nov = hari ini
        $this->adopsi('2026-11-24');                                                                      // jatuh tempo 1 Des: belum waktunya
        $this->adopsi('2026-11-01', [['2026-11-05', 'Meninggal', 'Sakit mendadak.']]);                    // pemantauan berakhir
        $this->adopsi('2026-04-01', [['2026-04-02', 'Sehat', null]]);                                     // masa 6 bulan sudah lewat
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/notifikasi/pengingat', [], $h)->assertOk()
            ->assertJsonPath('data.adopsi_ditinjau', 2)->assertJsonPath('data.pengingat_dibuat', 4)
            ->assertJsonPath('data.sudah_ada', 0)->assertJsonPath('data.dihentikan', 0)
            ->assertJsonPath('data.email.terkirim', 2);

        $this->assertSame(4, Notifikasi::where('jenis_notifikasi', 'Reminder Monitoring')->count());
        Mail::assertSent(NotifikasiMail::class, 2);

        $pesanTerlambat = Notifikasi::where('id_pengajuan', $terlambat->id_pengajuan)->where('kanal', 'WhatsApp')->firstOrFail();
        $this->assertSame('PENDING', $pesanTerlambat->status_pengiriman);                                  // WhatsApp menunggu dikirim manual
        $this->assertStringContainsString('Halo Siti,', $pesanTerlambat->isi_pesan);
        $this->assertStringContainsString('sudah terlambat 9 hari', $pesanTerlambat->isi_pesan);
        $this->assertStringContainsString('belum menerima kabar sejak serah terima', $pesanTerlambat->isi_pesan);
        $this->assertStringContainsString('pengingat satu arah', $pesanTerlambat->isi_pesan);              // BR-12

        $pesanHariIni = Notifikasi::where('id_pengajuan', $hariIni->id_pengajuan)->where('kanal', 'Email')->firstOrFail();
        $this->assertStringContainsString('jatuh tempo hari ini', $pesanHariIni->isi_pesan);
        $this->assertStringContainsString('tertanggal 2026-11-19', $pesanHariIni->isi_pesan);

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'notifikasi']);
    }

    public function test_pengingat_idempoten_dalam_satu_minggu_lalu_diulang_tiap_minggu(): void
    {
        $st = $this->adopsi('2026-11-10');
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/notifikasi/pengingat', [], $h)->assertJsonPath('data.pengingat_dibuat', 2);
        $this->postJson('/api/admin/notifikasi/pengingat', [], $h)->assertJsonPath('data.pengingat_dibuat', 0)->assertJsonPath('data.sudah_ada', 2)
            ->assertJsonPath('message', 'Tidak ada pengingat baru yang perlu dibuat.');
        Carbon::setTestNow(Carbon::create(2026, 11, 28, 8, 0, 0, 'Asia/Jakarta'));                        // masih minggu yang sama
        $this->postJson('/api/admin/notifikasi/pengingat', [], $h)->assertJsonPath('data.pengingat_dibuat', 0);
        $this->assertSame(2, Notifikasi::count());

        Carbon::setTestNow(Carbon::create(2026, 12, 3, 8, 0, 0, 'Asia/Jakarta'));                         // 16 hari lewat jatuh tempo: minggu ke-3
        $this->postJson('/api/admin/notifikasi/pengingat', [], $h)->assertJsonPath('data.pengingat_dibuat', 2);
        $this->assertSame(4, Notifikasi::count());

        // adopter memberi kabar -> jatuh tempo bergeser, siklus pengingat baru dimulai saat jatuh tempo berikutnya
        PascaAdopsi::create(['id_serah_terima' => $st->id_serah_terima, 'tanggal_update' => '2026-12-03', 'status_kondisi' => 'Sehat']);
        $this->postJson('/api/admin/notifikasi/pengingat', [], $h)->assertJsonPath('data.adopsi_ditinjau', 0);
        Carbon::setTestNow(Carbon::create(2026, 12, 10, 8, 0, 0, 'Asia/Jakarta'));
        $this->postJson('/api/admin/notifikasi/pengingat', [], $h)->assertJsonPath('data.pengingat_dibuat', 2);
        $this->assertSame(6, Notifikasi::count());
    }

    public function test_pengingat_berhenti_setelah_empat_kali_per_siklus(): void
    {
        $this->adopsi('2026-10-20');                                                                       // jatuh tempo 27 Okt, kini terlambat 30 hari
        $h = $this->asRole('admin');

        $this->postJson('/api/admin/notifikasi/pengingat', [], $h)->assertOk()
            ->assertJsonPath('data.adopsi_ditinjau', 1)->assertJsonPath('data.dihentikan', 1)->assertJsonPath('data.pengingat_dibuat', 0);
        $this->assertSame(0, Notifikasi::count());
    }

    public function test_pengingat_tidak_membocorkan_data_internal_dan_hanya_ke_adopter(): void
    {
        $st = $this->adopsi('2026-11-10', [], ['email' => 'siti@contoh.id', 'nomor_whatsapp' => '6281299990000']);
        $this->postJson('/api/admin/notifikasi/pengingat', [], $this->asRole('admin'))->assertOk();

        Mail::assertSent(NotifikasiMail::class, fn (NotifikasiMail $m) => $m->hasTo('siti@contoh.id') && ! str_contains($m->isi, '6281299990000') && ! str_contains($m->isi, 'siti@contoh.id'));
        $this->assertSame(1, Mail::sent(NotifikasiMail::class)->count());
        $this->assertSame([$st->id_pengajuan], Notifikasi::pluck('id_pengajuan')->unique()->values()->all());
    }

    // ------------------------------------------------------------ perintah & jadwal

    public function test_perintah_pengingat_dan_pengirim_berjalan_dari_konsol(): void
    {
        $this->adopsi('2026-11-10');

        $this->artisan('notifikasi:ingatkan')->expectsOutputToContain('pengingat baru 2')->assertSuccessful();
        $this->artisan('notifikasi:ingatkan')->expectsOutputToContain('pengingat baru 0, sudah ada 2')->assertSuccessful();
        $this->artisan('notifikasi:kirim')->expectsOutputToContain('terkirim 1, gagal 0')->assertSuccessful();

        Mail::assertSent(NotifikasiMail::class, 1);
    }

    public function test_perintah_notifikasi_terdaftar_di_penjadwal(): void
    {
        $perintah = collect(app(Schedule::class)->events())->map(fn ($e) => (string) $e->command)->implode(' | ');

        $this->assertStringContainsString('notifikasi:kirim', $perintah);
        $this->assertStringContainsString('notifikasi:ingatkan', $perintah);
    }

    public function test_pengiriman_tidak_menulis_audit_log_dari_perintah_konsol(): void
    {
        $this->notif($this->pengajuanBaru(), 'Email');
        $this->artisan('notifikasi:kirim')->assertSuccessful();

        $this->assertSame(0, AuditLog::where('nama_tabel', 'notifikasi')->count());
    }
}

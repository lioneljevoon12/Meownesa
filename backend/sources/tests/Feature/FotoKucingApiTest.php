<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FotoKucing;
use App\Models\Kucing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithApiAuth;
use Tests\Concerns\MembuatDataKucing;
use Tests\TestCase;

/** dev-backend-04: galeri multi-foto — upload, foto utama, hapus. */
class FotoKucingApiTest extends TestCase
{
    use InteractsWithApiAuth, MembuatDataKucing, RefreshDatabase;

    private Kucing $kucing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanWilayah();
        Storage::fake('kucing_foto');
        $this->kucing = $this->buatKucing(['kode_kucing' => 'KTT-050']);
    }

    private function unggah(UploadedFile $berkas, array $h, ?Kucing $k = null)
    {
        $k ??= $this->kucing;

        return $this->post("/api/admin/kucing/{$k->id_kucing}/foto", ['foto' => $berkas], $h + ['Accept' => 'application/json']);
    }

    private function jumlahUtama(?Kucing $k = null): int
    {
        return FotoKucing::where('id_kucing', ($k ?? $this->kucing)->id_kucing)->where('is_utama', true)->count();
    }

    // ------------------------------------------------------------ hak akses

    public function test_endpoint_foto_butuh_role_admin(): void
    {
        $foto = $this->buatFoto($this->kucing, true);
        $uri = "/api/admin/kucing/{$this->kucing->id_kucing}/foto";

        foreach ([null, 'superadmin', 'adopter'] as $role) {
            $h = $role ? $this->asRole($role) : [];
            $kode = $role ? 403 : 401;

            $this->post($uri, ['foto' => UploadedFile::fake()->image('a.jpg', 400, 400)], $h + ['Accept' => 'application/json'])->assertStatus($kode);
            $this->putJson("{$uri}/{$foto->id_foto_kucing}/utama", [], $h)->assertStatus($kode);
            $this->deleteJson("{$uri}/{$foto->id_foto_kucing}", [], $h)->assertStatus($kode);
        }
        $this->assertDatabaseCount('foto_kucing', 1);
    }

    // ---------------------------------------------------------------- upload

    public function test_foto_pertama_menjadi_utama_dan_berikutnya_bukan(): void
    {
        $h = $this->asRole('admin');

        $a = $this->unggah(UploadedFile::fake()->image('a.jpg', 800, 600), $h)->assertCreated()
            ->assertJsonPath('data.is_utama', true);
        $b = $this->unggah(UploadedFile::fake()->image('b.png', 800, 600), $h)->assertCreated()
            ->assertJsonPath('data.is_utama', false);

        $this->assertSame(1, $this->jumlahUtama());
        $this->assertDatabaseCount('foto_kucing', 2);

        $this->assertStringContainsString('/images/kucing/upload/ktt-050-', $a->json('data.url'));
        $path = FotoKucing::find($a->json('data.id_foto_kucing'))->url_foto;
        $this->assertStringStartsWith('images/kucing/upload/ktt-050-', $path);
        Storage::disk('kucing_foto')->assertExists('upload/'.basename($path));
        $this->assertNotEquals($a->json('data.url'), $b->json('data.url'));
    }

    public function test_foto_besar_dikompres_ke_jpeg_maks_1280_px_dengan_rasio_terjaga(): void
    {
        $res = $this->unggah(UploadedFile::fake()->image('besar.png', 3000, 2000), $this->asRole('admin'))->assertCreated();

        $path = FotoKucing::find($res->json('data.id_foto_kucing'))->url_foto;
        $isi = Storage::disk('kucing_foto')->get('upload/'.basename($path));
        [$lebar, $tinggi, $tipe] = getimagesizefromstring($isi);

        $this->assertSame(IMAGETYPE_JPEG, $tipe);          // PNG dikonversi ke JPEG
        $this->assertSame(1280, $lebar);
        $this->assertEqualsWithDelta(1280 * 2 / 3, $tinggi, 1);
    }

    public function test_foto_kecil_tidak_diperbesar(): void
    {
        $res = $this->unggah(UploadedFile::fake()->image('kecil.jpg', 400, 300), $this->asRole('admin'))->assertCreated();

        $path = FotoKucing::find($res->json('data.id_foto_kucing'))->url_foto;
        [$lebar, $tinggi] = getimagesizefromstring(Storage::disk('kucing_foto')->get('upload/'.basename($path)));
        $this->assertSame([400, 300], [$lebar, $tinggi]);
    }

    public function test_berkas_selain_gambar_jpg_png_webp_ditolak(): void
    {
        $h = $this->asRole('admin');

        $this->unggah(UploadedFile::fake()->create('dokumen.pdf', 50, 'application/pdf'), $h)->assertStatus(422)->assertJsonValidationErrors('foto');
        $this->unggah(UploadedFile::fake()->image('anim.gif', 400, 400), $h)->assertStatus(422);
        // berkas bukan gambar tapi berekstensi .jpg
        $this->unggah(UploadedFile::fake()->create('palsu.jpg', 50, 'image/jpeg'), $h)->assertStatus(422);

        $this->assertDatabaseCount('foto_kucing', 0);
        $this->assertSame([], Storage::disk('kucing_foto')->allFiles());
    }

    public function test_foto_terlalu_besar_atau_terlalu_kecil_ditolak(): void
    {
        $h = $this->asRole('admin');

        $this->unggah(UploadedFile::fake()->image('a.jpg', 400, 400)->size(6000), $h)->assertStatus(422)->assertJsonValidationErrors('foto');
        $this->unggah(UploadedFile::fake()->image('thumb.jpg', 100, 100), $h)->assertStatus(422)->assertJsonValidationErrors('foto');
        $this->post("/api/admin/kucing/{$this->kucing->id_kucing}/foto", [], $h + ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertDatabaseCount('foto_kucing', 0);
    }

    public function test_maksimal_10_foto_per_kucing(): void
    {
        $h = $this->asRole('admin');
        foreach (range(1, 10) as $i) {
            $this->buatFoto($this->kucing, $i === 1);
        }

        $this->unggah(UploadedFile::fake()->image('ke11.jpg', 400, 400), $h)->assertStatus(422)->assertJsonValidationErrors('foto');
        $this->assertDatabaseCount('foto_kucing', 10);
        $this->assertSame([], Storage::disk('kucing_foto')->allFiles());   // tidak ada berkas yatim
    }

    public function test_upload_ke_kucing_tidak_ada_404(): void
    {
        $this->post('/api/admin/kucing/99999/foto', ['foto' => UploadedFile::fake()->image('a.jpg', 400, 400)], $this->asRole('admin') + ['Accept' => 'application/json'])
            ->assertStatus(404);
    }

    // ----------------------------------------------------------- foto utama

    public function test_ganti_foto_utama_selalu_menyisakan_tepat_satu(): void
    {
        $a = $this->buatFoto($this->kucing, true);
        $b = $this->buatFoto($this->kucing, false);
        $c = $this->buatFoto($this->kucing, false);
        $h = $this->asRole('admin');

        $res = $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$c->id_foto_kucing}/utama", [], $h)->assertOk();

        $this->assertSame(1, $this->jumlahUtama());
        $this->assertTrue($c->fresh()->is_utama);
        $this->assertFalse($a->fresh()->is_utama);
        $this->assertSame($c->id_foto_kucing, $res->json('data.galeri.0.id_foto_kucing'));   // utama di urutan pertama
        $this->assertCount(3, $res->json('data.galeri'));

        // mengulang pada foto yang sama tetap satu
        $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$c->id_foto_kucing}/utama", [], $h)->assertOk();
        $this->assertSame(1, $this->jumlahUtama());
    }

    public function test_foto_milik_kucing_lain_tidak_bisa_dijadikan_utama_atau_dihapus(): void
    {
        $lain = $this->buatKucing();
        $fotoLain = $this->buatFoto($lain, true);
        $h = $this->asRole('admin');

        $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$fotoLain->id_foto_kucing}/utama", [], $h)->assertStatus(404);
        $this->deleteJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$fotoLain->id_foto_kucing}", [], $h)->assertStatus(404);

        $this->assertDatabaseHas('foto_kucing', ['id_foto_kucing' => $fotoLain->id_foto_kucing, 'is_utama' => true]);
    }

    // ----------------------------------------------------------------- hapus

    public function test_hapus_foto_utama_menaikkan_foto_tertua_yang_tersisa(): void
    {
        $a = $this->buatFoto($this->kucing, true);
        $b = $this->buatFoto($this->kucing, false);
        $c = $this->buatFoto($this->kucing, false);

        $this->deleteJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$a->id_foto_kucing}", [], $this->asRole('admin'))->assertOk();

        $this->assertDatabaseMissing('foto_kucing', ['id_foto_kucing' => $a->id_foto_kucing]);
        $this->assertTrue($b->fresh()->is_utama);
        $this->assertFalse($c->fresh()->is_utama);
        $this->assertSame(1, $this->jumlahUtama());
    }

    public function test_hapus_foto_biasa_tidak_mengubah_foto_utama_dan_foto_terakhir_menyisakan_nol(): void
    {
        $a = $this->buatFoto($this->kucing, true);
        $b = $this->buatFoto($this->kucing, false);
        $h = $this->asRole('admin');

        $this->deleteJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$b->id_foto_kucing}", [], $h)->assertOk();
        $this->assertTrue($a->fresh()->is_utama);

        $this->deleteJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$a->id_foto_kucing}", [], $h)->assertOk()->assertJsonCount(0, 'data.galeri');
        $this->assertDatabaseCount('foto_kucing', 0);
    }

    public function test_hapus_foto_menghapus_berkas_unggahan_tetapi_tidak_foto_bawaan_seeder(): void
    {
        $h = $this->asRole('admin');
        $res = $this->unggah(UploadedFile::fake()->image('a.jpg', 400, 400), $h)->assertCreated();
        $nama = basename(FotoKucing::find($res->json('data.id_foto_kucing'))->url_foto);
        Storage::disk('kucing_foto')->assertExists('upload/'.$nama);

        $this->deleteJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/".$res->json('data.id_foto_kucing'), [], $h)->assertOk();
        Storage::disk('kucing_foto')->assertMissing('upload/'.$nama);

        // foto bawaan seeder (di luar folder upload/) tidak boleh ikut terhapus dari disk
        $asli = public_path('images/kucing/KTT-001.jpg');
        $this->assertFileExists($asli);
        $bawaan = $this->buatFoto($this->kucing, true, 'images/kucing/KTT-001.jpg');
        $this->deleteJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$bawaan->id_foto_kucing}", [], $h)->assertOk();
        $this->assertFileExists($asli);
    }

    // ----------------------------------------------------------------- audit

    public function test_aksi_foto_tercatat_di_audit_log_dengan_id_foto(): void
    {
        $h = $this->asRole('admin');

        $a = $this->unggah(UploadedFile::fake()->image('a.jpg', 400, 400), $h)->assertCreated()->json('data.id_foto_kucing');
        $b = $this->unggah(UploadedFile::fake()->image('b.jpg', 400, 400), $h)->assertCreated()->json('data.id_foto_kucing');
        $this->putJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$b}/utama", [], $h)->assertOk();
        $this->deleteJson("/api/admin/kucing/{$this->kucing->id_kucing}/foto/{$a}", [], $h)->assertOk();

        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'CREATE', 'nama_tabel' => 'foto_kucing', 'id_data' => $a]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'UPDATE', 'nama_tabel' => 'foto_kucing', 'id_data' => $b]);
        $this->assertDatabaseHas('audit_log', ['aktivitas' => 'DELETE', 'nama_tabel' => 'foto_kucing', 'id_data' => $a]);
        $this->assertSame(4, AuditLog::where('nama_tabel', 'foto_kucing')->count());
    }
}

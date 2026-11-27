<?php

use App\Http\Controllers\AktivasiAkunController;
use App\Http\Controllers\ArtikelAdminController;
use App\Http\Controllers\ArtikelController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FotoKucingController;
use App\Http\Controllers\InfoPublikController;
use App\Http\Controllers\KampusController;
use App\Http\Controllers\KucingAdminController;
use App\Http\Controllers\KucingController;
use App\Http\Controllers\LokasiController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PascaAdopsiController;
use App\Http\Controllers\PemberianMakanController;
use App\Http\Controllers\PetaController;
use App\Http\Controllers\PengajuanAdopsiAdminController;
use App\Http\Controllers\PengajuanAdopsiController;
use App\Http\Controllers\RiwayatKesehatanAdminController;
use App\Http\Controllers\RiwayatKesehatanController;
use App\Http\Controllers\ScreeningAdopsiController;
use App\Http\Controllers\SerahTerimaController;
use App\Http\Controllers\SinkronController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API MeowNesa  (prefix /api)
|--------------------------------------------------------------------------
| Konvensi middleware:
|   - publik            : tanpa middleware
|   - /api/admin/*      : ['auth:sanctum', 'role:admin', 'audit:<tabel>']
|   - /api/superadmin/* : ['auth:sanctum', 'role:superadmin', 'audit:<tabel>']
*/

// ---- dev-backend-02: autentikasi ------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    // dev-backend-09: adopter mengatur password lewat tautan dari notifikasi serah terima (publik)
    Route::post('aktivasi', [AktivasiAkunController::class, 'aktifkan'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        // dev-backend-15: tukar token yang masih berlaku dengan yang baru (PWA/HP lapangan).
        Route::post('perpanjang', [AuthController::class, 'perpanjang'])->middleware('throttle:perpanjang');
    });
});

// ---- dev-backend-03: kampus & lokasi (publik) ------------------------------------
Route::get('kampus', [KampusController::class, 'index'])->middleware('cache.publik');
Route::get('lokasi', [LokasiController::class, 'index'])->middleware('cache.publik');
Route::get('lokasi/{id}', [LokasiController::class, 'show'])->whereNumber('id')->middleware('cache.publik');

// ---- dev-backend-04: katalog kucing (publik) -------------------------------------
Route::get('kucing', [KucingController::class, 'index'])->middleware('cache.publik');
Route::get('kucing/{id}', [KucingController::class, 'show'])->whereNumber('id')->middleware('cache.publik');

// ---- dev-backend-05: riwayat kesehatan (publik) ----------------------------------
Route::get('kucing/{id}/kesehatan', [RiwayatKesehatanController::class, 'index'])->whereNumber('id')->middleware('cache.publik');

// ---- dev-backend-13: peta persebaran kucing (publik; hanya titik lokasi umum + jumlah) ----
Route::get('peta/persebaran', [PetaController::class, 'persebaran'])->middleware('cache.publik');

// ---- dev-backend-12: artikel edukasi + informasi donasi/pelaporan (publik, hanya yang PUBLISHED) ----
// Rute statis (featured, kategori) harus didaftarkan sebelum {slug}.
Route::get('artikel', [ArtikelController::class, 'index'])->middleware('cache.publik');
Route::get('artikel/featured', [ArtikelController::class, 'featured'])->middleware('cache.publik');
Route::get('artikel/kategori', [ArtikelController::class, 'kategori'])->middleware('cache.publik');
Route::get('artikel/{slug}', [ArtikelController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('cache.publik');
Route::get('info-publik', [InfoPublikController::class, 'index'])->middleware('cache.publik');

// ---- dev-backend-07: pengajuan adopsi publik tanpa akun (dibatasi per IP) --------
Route::prefix('adopsi')->group(function () {
    Route::post('ajukan', [PengajuanAdopsiController::class, 'ajukan'])->middleware('throttle:adopsi-tulis');
    Route::post('upload-lampiran', [PengajuanAdopsiController::class, 'uploadLampiran'])->middleware('throttle:adopsi-tulis');
    Route::get('tracking/{kode}', [PengajuanAdopsiController::class, 'tracking'])->middleware('throttle:adopsi-tracking');
});

// ---- Admin Jaga Satwa ------------------------------------------------------------
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // dev-backend-03
    Route::middleware('audit:lokasi')->group(function () {
        Route::post('lokasi', [LokasiController::class, 'store']);
        Route::put('lokasi/{id}', [LokasiController::class, 'update'])->whereNumber('id');
        Route::delete('lokasi/{id}', [LokasiController::class, 'destroy'])->whereNumber('id');
    });

    // dev-backend-04: data kucing (admin melihat semua status)
    Route::get('kucing', [KucingAdminController::class, 'index']);
    Route::get('kucing/{id}', [KucingAdminController::class, 'show'])->whereNumber('id');

    Route::middleware('audit:kucing')->group(function () {
        Route::post('kucing', [KucingAdminController::class, 'store']);
        Route::put('kucing/{id}', [KucingAdminController::class, 'update'])->whereNumber('id');
        Route::delete('kucing/{id}', [KucingAdminController::class, 'destroy'])->whereNumber('id');
    });

    // dev-backend-04: galeri foto. Parameter bernama {kucing}/{foto} (bukan {id}) agar audit log
    // mencatat id foto dari respons, bukan id kucing.
    Route::middleware('audit:foto_kucing')->group(function () {
        Route::post('kucing/{kucing}/foto', [FotoKucingController::class, 'store'])->whereNumber('kucing');
        Route::put('kucing/{kucing}/foto/{foto}/utama', [FotoKucingController::class, 'setUtama'])->whereNumber(['kucing', 'foto']);
        Route::delete('kucing/{kucing}/foto/{foto}', [FotoKucingController::class, 'destroy'])->whereNumber(['kucing', 'foto']);
    });

    // dev-backend-08: antrean, screening, dan keputusan pengajuan adopsi. Hanya Admin Jaga Satwa (BR-05).
    // Screening memakai {pengajuan} (bukan {id}) agar audit log mencatat id_screening dari respons.
    Route::get('adopsi', [PengajuanAdopsiAdminController::class, 'index']);
    Route::get('adopsi/{id}', [PengajuanAdopsiAdminController::class, 'show'])->whereNumber('id');
    Route::get('adopsi/{id}/lampiran/{lampiran}', [PengajuanAdopsiAdminController::class, 'lampiran'])->whereNumber(['id', 'lampiran']);
    Route::put('adopsi/{id}/status', [PengajuanAdopsiAdminController::class, 'ubahStatus'])->whereNumber('id')->middleware('audit:pengajuan_adopsi,UBAH_STATUS');
    Route::post('adopsi/{pengajuan}/screening', [ScreeningAdopsiController::class, 'store'])->whereNumber('pengajuan')->middleware('audit:screening_adopsi');

    // dev-backend-09: serah terima + akun adopter (dibuat otomatis di langkah serah terima, BR-06/BR-07).
    // {pengajuan} pada POST agar audit mencatat id dari respons (id_serah_terima / id_pengajuan).
    Route::post('adopsi/{pengajuan}/serah-terima', [SerahTerimaController::class, 'store'])->whereNumber('pengajuan')->middleware('audit:serah_terima');
    Route::post('adopsi/{pengajuan}/kirim-ulang-aktivasi', [SerahTerimaController::class, 'kirimUlangAktivasi'])->whereNumber('pengajuan')->middleware('audit:pengajuan_adopsi,KIRIM_ULANG_AKTIVASI');
    Route::get('serah-terima/{id}', [SerahTerimaController::class, 'show'])->whereNumber('id');
    Route::get('serah-terima/{id}/berkas/{jenis}', [SerahTerimaController::class, 'berkas'])->whereNumber('id')->whereIn('jenis', ['dokumen_perjanjian', 'foto_serah_terima']);
    Route::post('serah-terima/{id}/berkas', [SerahTerimaController::class, 'uploadBerkas'])->whereNumber('id')->middleware('audit:serah_terima,UNGGAH_BERKAS');

    // dev-backend-10: pemantauan kondisi kucing setelah diadopsi (adopter memberi kabar via WhatsApp/CP, admin mencatat; BR-13).
    Route::get('pasca-adopsi', [PascaAdopsiController::class, 'index']);
    Route::get('pasca-adopsi/{idSerahTerima}/riwayat', [PascaAdopsiController::class, 'riwayat'])->whereNumber('idSerahTerima');
    Route::middleware('audit:pasca_adopsi')->group(function () {
        Route::post('pasca-adopsi', [PascaAdopsiController::class, 'store']);
        Route::put('pasca-adopsi/{id}', [PascaAdopsiController::class, 'update'])->whereNumber('id');
        Route::delete('pasca-adopsi/{id}', [PascaAdopsiController::class, 'destroy'])->whereNumber('id');
    });

    // dev-backend-11: histori notifikasi, kirim email, tandai WhatsApp manual, dan pemicu pengingat (BR-12).
    Route::get('notifikasi', [NotifikasiController::class, 'index']);
    Route::middleware('audit:notifikasi')->group(function () {
        Route::post('notifikasi/kirim', [NotifikasiController::class, 'kirim']);
        Route::post('notifikasi/pengingat', [NotifikasiController::class, 'pengingat']);
        Route::put('notifikasi/{id}/status', [NotifikasiController::class, 'tandai'])->whereNumber('id');
    });

    // dev-backend-15: dukungan offline PWA. Tarik perubahan (hanya baca) dan kirim catatan kesehatan massal.
    Route::get('sync/pull', [SinkronController::class, 'tarik']);
    Route::post('kesehatan/batch', [RiwayatKesehatanAdminController::class, 'batch'])->middleware('audit:riwayat_kesehatan,BATCH_CREATE');

    // dev-backend-13: statistik dashboard (hanya baca)
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/antrean-terbaru', [DashboardController::class, 'antreanTerbaru']);

    // dev-backend-12: CMS artikel (edukasi, kegiatan, kampanye kalung). Thumbnail diunggah/dihapus lewat rute tersendiri.
    Route::get('artikel', [ArtikelAdminController::class, 'index']);
    Route::get('artikel/{id}', [ArtikelAdminController::class, 'show'])->whereNumber('id');
    Route::middleware('audit:artikel')->group(function () {
        Route::post('artikel', [ArtikelAdminController::class, 'store']);
        Route::put('artikel/{id}', [ArtikelAdminController::class, 'update'])->whereNumber('id');
        Route::delete('artikel/{id}', [ArtikelAdminController::class, 'destroy'])->whereNumber('id');
    });
    // Di luar grup audit di atas: bila bersarang, setiap aksi tercatat dua kali (CREATE/DELETE + nama khususnya).
    Route::post('artikel/{id}/thumbnail', [ArtikelAdminController::class, 'uploadThumbnail'])->whereNumber('id')->middleware('audit:artikel,UNGGAH_THUMBNAIL');
    Route::delete('artikel/{id}/thumbnail', [ArtikelAdminController::class, 'hapusThumbnail'])->whereNumber('id')->middleware('audit:artikel,HAPUS_THUMBNAIL');

    // dev-backend-05: riwayat kesehatan. POST memakai {kucing} agar audit mencatat id_kesehatan dari
    // respons; PUT/DELETE memakai {id} (= id_kesehatan).
    // dev-backend-06: feeding log (pengganti spreadsheet presensi). Tidak ada endpoint publik.
    Route::get('feeding', [PemberianMakanController::class, 'index']);
    Route::get('feeding/rekap', [PemberianMakanController::class, 'rekap']);
    Route::post('feeding/batch', [PemberianMakanController::class, 'batch'])->middleware('audit:pemberian_makan,BATCH_CREATE');
    Route::middleware('audit:pemberian_makan')->group(function () {
        Route::post('feeding', [PemberianMakanController::class, 'store']);
        Route::put('feeding/{id}', [PemberianMakanController::class, 'update'])->whereNumber('id');
        Route::delete('feeding/{id}', [PemberianMakanController::class, 'destroy'])->whereNumber('id');
    });

    Route::get('kucing/{kucing}/kesehatan', [RiwayatKesehatanAdminController::class, 'index'])->whereNumber('kucing');
    Route::middleware('audit:riwayat_kesehatan')->group(function () {
        Route::post('kucing/{kucing}/kesehatan', [RiwayatKesehatanAdminController::class, 'store'])->whereNumber('kucing');
        Route::put('kesehatan/{id}', [RiwayatKesehatanAdminController::class, 'update'])->whereNumber('id');
        Route::delete('kesehatan/{id}', [RiwayatKesehatanAdminController::class, 'destroy'])->whereNumber('id');
    });
});

// ---- Super Admin (dev-backend-14) ------------------------------------------------
// Aspek teknis sistem saja: akun pengurus, hak akses, audit log. Tidak ada rute operasional/adopsi di sini.
Route::prefix('superadmin')->middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
    Route::get('hak-akses', [SuperAdminController::class, 'hakAkses']);
    Route::get('users', [SuperAdminController::class, 'index']);
    Route::get('users/{id}', [SuperAdminController::class, 'show'])->whereNumber('id');
    Route::post('users', [SuperAdminController::class, 'store'])->middleware('audit:users');
    Route::put('users/{id}', [SuperAdminController::class, 'update'])->whereNumber('id')->middleware('audit:users');
    Route::put('users/{id}/status', [SuperAdminController::class, 'ubahStatus'])->whereNumber('id')->middleware('audit:users,UBAH_STATUS');
    Route::post('users/{id}/reset-password', [SuperAdminController::class, 'resetPassword'])->whereNumber('id')->middleware('audit:users,RESET_PASSWORD');

    // Audit log: HANYA baca (tidak ada rute ubah/hapus).
    Route::get('audit-logs/opsi', [AuditLogController::class, 'opsi']);
    Route::get('audit-logs', [AuditLogController::class, 'index']);
});

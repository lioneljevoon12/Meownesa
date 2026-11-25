<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FotoKucingController;
use App\Http\Controllers\KampusController;
use App\Http\Controllers\KucingAdminController;
use App\Http\Controllers\KucingController;
use App\Http\Controllers\LokasiController;
use App\Http\Controllers\PemberianMakanController;
use App\Http\Controllers\RiwayatKesehatanAdminController;
use App\Http\Controllers\RiwayatKesehatanController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// ---- dev-backend-03: kampus & lokasi (publik) ------------------------------------
Route::get('kampus', [KampusController::class, 'index']);
Route::get('lokasi', [LokasiController::class, 'index']);
Route::get('lokasi/{id}', [LokasiController::class, 'show'])->whereNumber('id');

// ---- dev-backend-04: katalog kucing (publik) -------------------------------------
Route::get('kucing', [KucingController::class, 'index']);
Route::get('kucing/{id}', [KucingController::class, 'show'])->whereNumber('id');

// ---- dev-backend-05: riwayat kesehatan (publik) ----------------------------------
Route::get('kucing/{id}/kesehatan', [RiwayatKesehatanController::class, 'index'])->whereNumber('id');

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

<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KampusController;
use App\Http\Controllers\LokasiController;
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

// ---- Admin Jaga Satwa ------------------------------------------------------------
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::middleware('audit:lokasi')->group(function () {
        Route::post('lokasi', [LokasiController::class, 'store']);
        Route::put('lokasi/{id}', [LokasiController::class, 'update'])->whereNumber('id');
        Route::delete('lokasi/{id}', [LokasiController::class, 'destroy'])->whereNumber('id');
    });
});

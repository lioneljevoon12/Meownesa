<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API MeowNesa  (prefix /api)
|--------------------------------------------------------------------------
| Konvensi middleware untuk modul berikutnya:
|   - publik            : tanpa middleware
|   - /api/admin/*      : ['auth:sanctum', 'role:admin', 'audit:<tabel>']
|   - /api/superadmin/* : ['auth:sanctum', 'role:superadmin', 'audit:<tabel>']
*/

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

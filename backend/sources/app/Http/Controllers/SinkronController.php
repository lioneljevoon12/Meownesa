<?php

namespace App\Http\Controllers;

use App\Http\Requests\TarikSinkronRequest;
use App\Services\SinkronService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * Sinkronisasi data untuk PWA offline (dev-backend-15). Route: role:admin. Hanya membaca, jadi tanpa audit log.
 * Penulisan dari HP memakai endpoint biasa dengan `client_uuid` (feeding, kesehatan, foto) supaya aman diulang.
 */
class SinkronController extends Controller
{
    public function __construct(private readonly SinkronService $sinkron)
    {
    }

    /** Tarik semua data yang berubah sejak kursor (atau semuanya bila tanpa `sejak`). */
    public function tarik(TarikSinkronRequest $request): JsonResponse
    {
        $sejak = $request->validated('sejak') ? Carbon::parse($request->validated('sejak')) : null;

        return response()->json([
            'success' => true,
            'data'    => $this->sinkron->tarik($sejak, $request->validated('hari_feeding')),
        ])->header('Cache-Control', 'no-store');   // data admin, jangan disimpan cache bersama
    }
}

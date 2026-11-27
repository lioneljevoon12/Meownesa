<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard Admin Jaga Satwa (MEOW-F-38, F-40). Route: role:admin. Hanya membaca, jadi tanpa audit log.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    /** Enam metrik utama + peringatan operasional. */
    public function stats(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->dashboard->statistik()]);
    }

    /** Widget antrean adopsi yang butuh tindakan (maks. `limit`, bawaan 5, batas 20). */
    public function antreanTerbaru(Request $request): JsonResponse
    {
        $batas = max(1, min(20, $request->integer('limit', 5)));

        return response()->json(['success' => true, 'data' => $this->dashboard->antreanTerbaru($batas)]);
    }
}

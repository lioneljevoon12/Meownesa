<?php

namespace App\Http\Controllers;

use App\Http\Resources\RiwayatKesehatanResource;
use App\Models\Kucing;
use Illuminate\Http\JsonResponse;

/**
 * Riwayat kesehatan dan perawatan untuk PUBLIK (MEOW-F-13). Hanya untuk kucing yang tampil di katalog
 * publik; kucing berstatus lain dianggap tidak ada (404), sama seperti detail kucing.
 */
class RiwayatKesehatanController extends Controller
{
    public function index(int $id): JsonResponse
    {
        $kucing = Kucing::query()->whereIn('status_kucing', Kucing::STATUS_PUBLIK)->findOrFail($id);

        $riwayat = $kucing->riwayatKesehatan()
            ->orderByDesc('tanggal')
            ->orderByDesc('id_kesehatan')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => RiwayatKesehatanResource::collection($riwayat),
        ]);
    }
}

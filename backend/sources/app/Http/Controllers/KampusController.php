<?php

namespace App\Http\Controllers;

use App\Http\Resources\KampusResource;
use App\Models\Kampus;
use App\Models\Kucing;
use Illuminate\Http\JsonResponse;

/** Data kampus untuk publik (Ketintang & Lidah Wetan). */
class KampusController extends Controller
{
    public function index(): JsonResponse
    {
        $kampus = Kampus::query()
            ->withCount(['lokasi', 'kucing' => fn ($q) => $q->whereIn('status_kucing', Kucing::STATUS_DI_KAMPUS)])
            ->orderBy('id_kampus')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => KampusResource::collection($kampus),
        ]);
    }
}

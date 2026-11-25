<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterKucingRequest;
use App\Http\Resources\KucingResource;
use App\Models\Kucing;
use Illuminate\Http\JsonResponse;

/**
 * Katalog kucing PUBLIK (MEOW-F-01, F-02, F-06). Hanya status publik (Tersedia, Terdata) yang
 * terlihat; kucing berstatus lain dianggap tidak ada (404) agar tidak membocorkan data internal.
 */
class KucingController extends Controller
{
    public function index(FilterKucingRequest $request): JsonResponse
    {
        $filter = $request->validated();

        $halaman = Kucing::query()
            ->with(['lokasi.kampus', 'fotoUtama'])
            ->whereIn('status_kucing', Kucing::STATUS_PUBLIK)
            ->filter($filter)
            ->paginate($filter['per_page'] ?? $request->perHalamanBawaan())
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data'    => KucingResource::collection($halaman->getCollection()),
            'meta'    => [
                'halaman'       => $halaman->currentPage(),
                'halaman_akhir' => $halaman->lastPage(),
                'per_halaman'   => $halaman->perPage(),
                'total'         => $halaman->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $kucing = Kucing::query()
            ->with(['lokasi.kampus', 'fotoUtama', 'fotoKucing' => fn ($q) => $q->orderByDesc('is_utama')->orderBy('id_foto_kucing')])
            ->whereIn('status_kucing', Kucing::STATUS_PUBLIK)
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => new KucingResource($kucing)]);
    }
}

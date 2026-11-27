<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterArtikelRequest;
use App\Http\Resources\ArtikelDetailResource;
use App\Http\Resources\ArtikelResource;
use App\Models\Artikel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Artikel edukasi untuk publik (MEOW-F-33 s/d F-35). Hanya artikel berstatus PUBLISHED yang pernah terlihat;
 * draft dan arsip dijawab 404 seolah tidak ada.
 */
class ArtikelController extends Controller
{
    /** Daftar artikel terbit: yang disematkan dulu, lalu terbaru. Filter: kategori, q. */
    public function index(FilterArtikelRequest $request): JsonResponse
    {
        $f = $request->validated();

        $halaman = Artikel::diterbitkan()
            ->filter($f)
            ->urutanTampil()
            ->paginate($f['per_page'] ?? 9, ['*'], 'page', $f['page'] ?? 1);

        return response()->json([
            'success' => true,
            'data'    => ArtikelResource::collection($halaman->getCollection())->resolve(),
            'meta'    => [
                'halaman'       => $halaman->currentPage(),
                'halaman_akhir' => $halaman->lastPage(),
                'per_halaman'   => $halaman->perPage(),
                'total'         => $halaman->total(),
            ],
        ]);
    }

    /** Artikel unggulan untuk banner landing page (maks. `limit`, bawaan 5, batas 10). */
    public function featured(Request $request): JsonResponse
    {
        $batas = max(1, min(10, $request->integer('limit', 5)));

        $artikel = Artikel::diterbitkan()->where('is_featured', true)->urutanTampil()->limit($batas)->get();

        return response()->json(['success' => true, 'data' => ArtikelResource::collection($artikel)->resolve()]);
    }

    /** Kategori beserta jumlah artikel terbit (untuk tombol filter). */
    public function kategori(): JsonResponse
    {
        $jumlah = Artikel::diterbitkan()->selectRaw('kategori, count(*) as jumlah')->groupBy('kategori')->pluck('jumlah', 'kategori');

        return response()->json([
            'success' => true,
            'data'    => array_map(fn (string $k) => ['kategori' => $k, 'jumlah' => (int) ($jumlah[$k] ?? 0)], Artikel::KATEGORI),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $artikel = Artikel::diterbitkan()->where('slug', $slug)->firstOrFail();

        return response()->json(['success' => true, 'data' => (new ArtikelDetailResource($artikel))->resolve()]);
    }
}

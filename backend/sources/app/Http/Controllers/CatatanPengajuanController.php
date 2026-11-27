<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCatatanPengajuanRequest;
use App\Http\Resources\CatatanPengajuanResource;
use App\Models\CatatanPengajuan;
use App\Models\PengajuanAdopsi;
use Illuminate\Http\JsonResponse;

/**
 * Catatan internal per pengajuan adopsi, termasuk hasil/rencana kunjungan rumah (dev-backend-17).
 * Route: role:admin + audit:catatan_pengajuan. TIDAK pernah muncul di pelacakan publik maupun di portal adopter.
 */
class CatatanPengajuanController extends Controller
{
    public function index(int $pengajuan): JsonResponse
    {
        PengajuanAdopsi::findOrFail($pengajuan);

        $daftar = CatatanPengajuan::with('penulis:id_user,nama')->where('id_pengajuan', $pengajuan)
            ->orderByDesc('created_at')->orderByDesc('id_catatan')->limit(200)->get();

        return response()->json(['success' => true, 'data' => CatatanPengajuanResource::collection($daftar)->resolve()]);
    }

    public function store(StoreCatatanPengajuanRequest $request, int $pengajuan): JsonResponse
    {
        PengajuanAdopsi::findOrFail($pengajuan);

        $catatan = CatatanPengajuan::create([
            'id_pengajuan'      => $pengajuan,
            'id_user'           => $request->user()->id_user,
            'jenis'             => $request->validated('jenis') ?? 'Catatan',
            'isi'               => $request->validated('isi'),
            'tanggal_kunjungan' => $request->validated('tanggal_kunjungan'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Catatan berhasil ditambahkan.',
            'data'    => (new CatatanPengajuanResource($catatan->load('penulis:id_user,nama')))->resolve(),
        ], 201);
    }

    /** Hanya penulisnya yang boleh menghapus catatannya (jejak keputusan tidak boleh dihapus orang lain). */
    public function destroy(int $pengajuan, int $catatan): JsonResponse
    {
        $c = CatatanPengajuan::where('id_pengajuan', $pengajuan)->whereKey($catatan)->firstOrFail();

        if ($c->id_user !== request()->user()->id_user) {
            return response()->json(['success' => false, 'message' => 'Catatan hanya dapat dihapus oleh penulisnya.'], 403);
        }

        $c->delete();

        return response()->json(['success' => true, 'message' => 'Catatan berhasil dihapus.', 'data' => ['id_catatan' => $catatan]]);
    }
}

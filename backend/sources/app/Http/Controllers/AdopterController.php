<?php

namespace App\Http\Controllers;

use App\Http\Requests\KirimLaporanAdopterRequest;
use App\Services\LaporanAdopterService;
use App\Services\PortalAdopterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portal adopter (dev-backend-17). Route: role:adopter + audit:laporan_adopter untuk pengiriman laporan.
 * Adopter hanya melihat adopsi MILIKNYA; adopsi orang lain dijawab 404.
 */
class AdopterController extends Controller
{
    public function __construct(
        private readonly PortalAdopterService $portal,
        private readonly LaporanAdopterService $laporan,
    ) {
    }

    /** Kucing-kucing yang saya adopsi, beserta jadwal kabar berikutnya. */
    public function index(Request $request): JsonResponse
    {
        $daftar = $this->portal->adopsiSaya($request->user())->map(fn ($st) => $this->portal->ringkas($st))->values();

        return response()->json(['success' => true, 'data' => $daftar]);
    }

    /** Detail satu adopsi: riwayat kondisi resmi dan laporan yang pernah saya kirim. */
    public function show(Request $request, int $id): JsonResponse
    {
        $st = $this->portal->milik($request->user(), $id);

        return response()->json(['success' => true, 'data' => $this->portal->detail($st)]);
    }

    /**
     * Kirim kabar kondisi kucing. Masuk kotak masuk admin (MENUNGGU); admin yang mencatatnya ke riwayat resmi
     * (MEOW-BR-13). Dibatasi throttle:laporan-adopter.
     */
    public function kirimLaporan(KirimLaporanAdopterRequest $request, int $serahTerima): JsonResponse
    {
        $user = $request->user();
        $st = $this->portal->milik($user, $serahTerima);

        $laporan = $this->laporan->kirim($st, $user, $request->validated());

        // id_laporan harus kunci pertama: dipakai middleware audit sebagai id_data.
        return response()->json([
            'success' => true,
            'message' => 'Terima kasih! Kabar Anda sudah kami terima dan akan dicatat oleh pengurus Jaga Satwa.',
            'data'    => [
                'id_laporan'      => $laporan->id_laporan,
                'id_serah_terima' => $laporan->id_serah_terima,
                'tanggal'         => $laporan->tanggal_laporan->format('Y-m-d'),
                'kondisi'         => $laporan->status_kondisi,
                'status'          => $laporan->status_laporan,
            ],
        ], 201);
    }
}

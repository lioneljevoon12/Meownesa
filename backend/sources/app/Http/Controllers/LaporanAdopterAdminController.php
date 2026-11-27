<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterLaporanAdopterRequest;
use App\Http\Requests\TinjauLaporanAdopterRequest;
use App\Http\Resources\LaporanAdopterAdminResource;
use App\Models\LaporanAdopter;
use App\Services\LaporanAdopterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Kotak masuk laporan kondisi dari adopter (dev-backend-17, MEOW-BR-13). Route: role:admin + audit:laporan_adopter.
 * Admin yang mencatat laporan ke riwayat pemantauan resmi (atau menolaknya dengan alasan).
 */
class LaporanAdopterAdminController extends Controller
{
    public function __construct(private readonly LaporanAdopterService $laporan)
    {
    }

    /** Terlama dulu untuk yang MENUNGGU (antrean), terbaru dulu untuk yang sudah ditinjau. */
    public function index(FilterLaporanAdopterRequest $request): JsonResponse
    {
        $f = $request->validated();
        $status = $f['status'] ?? null;

        $halaman = LaporanAdopter::with(['serahTerima.pengajuanAdopsi.kucing'])
            ->when($status, fn (Builder $q, $v) => $q->where('status_laporan', $v))
            ->when($status === LaporanAdopter::MENUNGGU,
                fn (Builder $q) => $q->orderBy('id_laporan'),
                fn (Builder $q) => $q->orderByDesc('id_laporan'))
            ->paginate($f['per_page'] ?? 20, ['*'], 'page', $f['page'] ?? 1);

        $perStatus = array_fill_keys(LaporanAdopter::STATUS, 0);
        foreach (LaporanAdopter::selectRaw('status_laporan, count(*) as jumlah')->groupBy('status_laporan')->pluck('jumlah', 'status_laporan') as $s => $jumlah) {
            $perStatus[$s] = (int) $jumlah;
        }

        return response()->json([
            'success' => true,
            'data'    => LaporanAdopterAdminResource::collection($halaman->getCollection())->resolve(),
            'meta'    => [
                'halaman'           => $halaman->currentPage(),
                'halaman_akhir'     => $halaman->lastPage(),
                'per_halaman'       => $halaman->perPage(),
                'total'             => $halaman->total(),
                'jumlah_per_status' => $perStatus,
            ],
        ]);
    }

    /** Catat laporan sebagai pembaruan pemantauan resmi (jadwal kabar berikutnya ikut bergeser). */
    public function catat(TinjauLaporanAdopterRequest $request, int $id): JsonResponse
    {
        [$laporan, $pasca] = $this->laporan->catat($id, $request->user(), $request->validated('catatan_admin'));

        return response()->json([
            'success' => true,
            'message' => 'Laporan dicatat sebagai pembaruan pemantauan.',
            'data'    => (new LaporanAdopterAdminResource($laporan->load('serahTerima.pengajuanAdopsi.kucing')))->resolve() + ['id_pasca_adopsi' => $pasca->id_pasca_adopsi],
        ]);
    }

    public function tolak(TinjauLaporanAdopterRequest $request, int $id): JsonResponse
    {
        $laporan = $this->laporan->tolakLaporan($id, $request->user(), $request->validated('alasan'));

        return response()->json([
            'success' => true,
            'message' => 'Laporan ditolak. Alasannya terlihat oleh adopter.',
            'data'    => (new LaporanAdopterAdminResource($laporan->load('serahTerima.pengajuanAdopsi.kucing')))->resolve(),
        ]);
    }
}

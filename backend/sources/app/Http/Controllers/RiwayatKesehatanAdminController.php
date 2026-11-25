<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRiwayatKesehatanRequest;
use App\Http\Requests\UpdateRiwayatKesehatanRequest;
use App\Http\Resources\RiwayatKesehatanAdminResource;
use App\Models\Kucing;
use App\Models\RiwayatKesehatan;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan kesehatan dan perawatan oleh Admin Jaga Satwa (MEOW-F-10, F-12, F-13).
 * Route: role:admin + audit:riwayat_kesehatan. Klinik tidak punya akun (MEOW-BR-09).
 *
 * Sinkronisasi: mencatat/mengubah menjadi tindakan "Sterilisasi" otomatis menetapkan
 * kucing.status_steril = Sudah. Arah sebaliknya TIDAK otomatis: menghapus atau mengubah catatan
 * sterilisasi tidak mengembalikan status ke Belum, karena status "Sudah" juga bisa berasal dari
 * data presensi (tanpa catatan medis) sehingga kebenaran sebelumnya tidak diketahui sistem.
 */
class RiwayatKesehatanAdminController extends Controller
{
    public function index(int $kucing): JsonResponse
    {
        $k = Kucing::findOrFail($kucing);

        $riwayat = $k->riwayatKesehatan()->orderByDesc('tanggal')->orderByDesc('id_kesehatan')->get();

        return response()->json([
            'success' => true,
            'data'    => RiwayatKesehatanAdminResource::collection($riwayat),
        ]);
    }

    public function store(StoreRiwayatKesehatanRequest $request, int $kucing): JsonResponse
    {
        $catatan = DB::transaction(function () use ($request, $kucing) {
            $k = Kucing::whereKey($kucing)->lockForUpdate()->firstOrFail();

            if ($request->input('jenis_tindakan') === RiwayatKesehatan::JENIS_STERILISASI) {
                $this->pastikanSterilisasiTunggal($k->id_kucing);
            }

            $catatan = $k->riwayatKesehatan()->create($request->validated());
            $this->sinkronSteril($k, $catatan, $request);

            return $catatan;
        });

        return response()->json([
            'success' => true,
            'message' => 'Catatan kesehatan berhasil ditambahkan.',
            'data'    => new RiwayatKesehatanAdminResource($catatan),
        ], 201);
    }

    public function update(UpdateRiwayatKesehatanRequest $request, int $id): JsonResponse
    {
        $catatan = DB::transaction(function () use ($request, $id) {
            $catatan = RiwayatKesehatan::findOrFail($id);
            $k = Kucing::whereKey($catatan->id_kucing)->lockForUpdate()->firstOrFail();

            $catatan->fill($request->validated());

            if ($catatan->jenis_tindakan === RiwayatKesehatan::JENIS_STERILISASI) {
                $this->pastikanSterilisasiTunggal($k->id_kucing, $catatan->id_kesehatan);
            }

            $catatan->save();
            $this->sinkronSteril($k, $catatan, $request);

            return $catatan->refresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Catatan kesehatan berhasil diperbarui.',
            'data'    => new RiwayatKesehatanAdminResource($catatan),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $catatan = RiwayatKesehatan::findOrFail($id);
        $catatan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catatan kesehatan berhasil dihapus.',
            'data'    => ['id_kesehatan' => $id, 'id_kucing' => $catatan->id_kucing],
        ]);
    }

    private function sinkronSteril(Kucing $k, RiwayatKesehatan $catatan, Request $request): void
    {
        if ($catatan->jenis_tindakan !== RiwayatKesehatan::JENIS_STERILISASI || $k->status_steril === 'Sudah') {
            return;
        }

        $k->update(['status_steril' => 'Sudah']);

        // Perubahan data kucing akibat sinkronisasi juga dicatat agar bisa ditelusuri.
        AuditLogger::record($request->user(), 'SINKRON_STERIL', 'kucing', $k->id_kucing);
    }

    /** Pengecekan ulang di dalam transaksi (baris kucing terkunci) agar permintaan bersamaan tidak lolos. */
    private function pastikanSterilisasiTunggal(int $idKucing, ?int $abaikanId = null): void
    {
        $ada = RiwayatKesehatan::where('id_kucing', $idKucing)
            ->where('jenis_tindakan', RiwayatKesehatan::JENIS_STERILISASI)
            ->when($abaikanId, fn ($q) => $q->whereKeyNot($abaikanId))
            ->exists();

        if ($ada) {
            throw ValidationException::withMessages([
                'jenis_tindakan' => 'Kucing ini sudah punya catatan sterilisasi. Ubah catatan yang ada, bukan menambah baru.',
            ]);
        }
    }
}

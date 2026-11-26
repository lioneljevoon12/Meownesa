<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RecordAuditLog;
use App\Http\Requests\StoreScreeningRequest;
use App\Http\Resources\ScreeningResource;
use App\Models\PemberianMakan;
use App\Models\PengajuanAdopsi;
use App\Models\ScreeningAdopsi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hasil pemeriksaan calon adopter (MEOW-F-17, F-18). Route: role:admin + audit:screening_adopsi.
 * Satu pengajuan satu hasil: menyimpan lagi memperbarui hasil yang ada. `id_user` selalu admin yang
 * terakhir menyimpan (penanggung jawab); riwayat perubahan terlacak di audit log.
 */
class ScreeningAdopsiController extends Controller
{
    public function store(StoreScreeningRequest $request, int $pengajuan): JsonResponse
    {
        $data = $request->validated();

        [$screening, $dibuat] = DB::transaction(function () use ($data, $pengajuan, $request) {
            $p = PengajuanAdopsi::whereKey($pengajuan)->lockForUpdate()->firstOrFail();

            // Hasil hanya boleh diubah selagi pemeriksaan berlangsung; setelah diputuskan dikunci agar dasar
            // keputusan tidak berubah diam-diam. Untuk mengoreksi, kembalikan status ke SCREENING lebih dulu.
            if ($p->status_pengajuan !== 'SCREENING') {
                throw ValidationException::withMessages([
                    'status' => 'Hasil pemeriksaan hanya dapat diisi saat pengajuan berstatus SCREENING (status saat ini: '.$p->status_pengajuan.'). Mulai pemeriksaan terlebih dahulu.',
                ]);
            }

            $screening = ScreeningAdopsi::firstOrNew(['id_pengajuan' => $p->id_pengajuan]);
            $dibuat = ! $screening->exists;

            $screening->fill($data);
            $screening->id_user = $request->user()->id_user;
            if ($dibuat && empty($data['tanggal_screening'])) {
                $screening->tanggal_screening = Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString();
            }
            $screening->save();

            return [$screening, $dibuat];
        });

        if (! $dibuat) {
            // Endpoint upsert via POST: catat sebagai UPDATE, bukan CREATE. request() = instans yang dilihat middleware.
            request()->attributes->set(RecordAuditLog::AKTIVITAS_KHUSUS, 'UPDATE');
        }

        return response()->json([
            'success' => true,
            'message' => $dibuat ? 'Hasil pemeriksaan berhasil disimpan.' : 'Hasil pemeriksaan berhasil diperbarui.',
            'data'    => new ScreeningResource($screening->load('admin')),
        ], $dibuat ? 201 : 200);
    }
}

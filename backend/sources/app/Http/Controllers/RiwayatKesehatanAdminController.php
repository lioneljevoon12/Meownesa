<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RecordAuditLog;
use App\Http\Requests\StoreRiwayatKesehatanRequest;
use App\Http\Requests\UpdateRiwayatKesehatanRequest;
use App\Http\Resources\RiwayatKesehatanAdminResource;
use App\Models\Kucing;
use App\Models\RiwayatKesehatan;
use App\Services\KesehatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan kesehatan dan perawatan oleh Admin Jaga Satwa (MEOW-F-10, F-12, F-13).
 * Route: role:admin + audit:riwayat_kesehatan. Klinik tidak punya akun (MEOW-BR-09).
 * Aturan penyimpanan (sinkron status steril, idempotensi client_uuid) ada di {@see KesehatanService}.
 */
class RiwayatKesehatanAdminController extends Controller
{
    public function __construct(private readonly KesehatanService $kesehatan)
    {
    }

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
        [$catatan, $dibuat] = $this->kesehatan->simpan($kucing, $request->validated(), $request->user());

        if (! $dibuat) {
            // Pengiriman ulang: tidak ada data baru, jadi jangan dicatat sebagai CREATE di audit log.
            // Memakai request() (instans yang dilihat middleware); $request di sini salinan FormRequest.
            request()->attributes->set(RecordAuditLog::LEWATI, true);
        }

        return response()->json([
            'success' => true,
            'message' => $dibuat ? 'Catatan kesehatan berhasil ditambahkan.' : 'Catatan ini sudah tersimpan sebelumnya (pengiriman ulang diabaikan).',
            'data'    => new RiwayatKesehatanAdminResource($catatan),
        ], $dibuat ? 201 : 200);
    }

    /**
     * Sinkronisasi massal dari HP: hingga 50 catatan dalam satu permintaan, tiap catatan diproses mandiri.
     * Respons 200 dengan hasil per catatan: dibuat | sudah_ada (client_uuid pernah dikirim) | ditolak.
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'entries'   => ['required', 'array', 'min:1', 'max:50'],
            'entries.*' => ['array'],
        ], [
            'entries.required' => 'Daftar catatan (entries) wajib diisi.',
            'entries.array'    => 'entries harus berupa daftar catatan.',
            'entries.min'      => 'Minimal 1 catatan per permintaan.',
            'entries.max'      => 'Maksimal 50 catatan per permintaan. Kirim dalam beberapa batch.',
            'entries.*.array'  => 'Setiap catatan harus berupa objek.',
        ]);

        $hasil = [];
        $ringkasan = ['dibuat' => 0, 'sudah_ada' => 0, 'ditolak' => 0];

        foreach ($request->input('entries') as $i => $mentah) {
            $item = ['index' => $i, 'client_uuid' => $mentah['client_uuid'] ?? null];

            if (isset($mentah['catatan']) && is_string($mentah['catatan'])) {
                $mentah['catatan'] = trim($mentah['catatan']);
            }

            $validator = Validator::make($mentah, $this->kesehatan->aturanBatch(), [
                'jenis_tindakan.in' => 'Jenis tindakan harus salah satu dari: '.implode(', ', RiwayatKesehatan::JENIS).'.',
                'id_kucing.exists'  => 'Kucing tidak ditemukan.',
                'uuid'              => 'client_uuid harus berupa UUID yang valid.',
            ]);
            $galat = $validator->fails() ? $validator->errors()->toArray() : [];

            $data = [];
            if (! $galat) {
                $data = $validator->validated();
                foreach ($this->kesehatan->periksa($data) as $field => $pesan) {
                    $galat[$field] = [$pesan];
                }
            }

            if (! $galat) {
                try {
                    [$catatan, $dibuat] = $this->kesehatan->simpan((int) $data['id_kucing'], $data, $request->user());
                    $item += ['status' => $dibuat ? 'dibuat' : 'sudah_ada', 'id_kesehatan' => $catatan->id_kesehatan];
                } catch (ValidationException $e) {
                    $galat = $e->errors();
                }
            }

            if ($galat) {
                $item += ['status' => 'ditolak', 'errors' => $galat];
            }

            $ringkasan[$item['status']]++;
            $hasil[] = $item;
        }

        // Semuanya pengiriman ulang atau ditolak: tidak ada perubahan data, jadi tidak dicatat sebagai aksi di audit log.
        if ($ringkasan['dibuat'] === 0) {
            request()->attributes->set(RecordAuditLog::LEWATI, true);
        }

        return response()->json([
            'success' => true,
            'message' => "{$ringkasan['dibuat']} dibuat, {$ringkasan['sudah_ada']} sudah ada, {$ringkasan['ditolak']} ditolak.",
            'data'    => ['ringkasan' => $ringkasan, 'hasil' => $hasil],
        ]);
    }

    public function update(UpdateRiwayatKesehatanRequest $request, int $id): JsonResponse
    {
        $catatan = DB::transaction(function () use ($request, $id) {
            $catatan = RiwayatKesehatan::findOrFail($id);
            $k = Kucing::whereKey($catatan->id_kucing)->lockForUpdate()->firstOrFail();

            $catatan->fill($request->validated());

            if ($catatan->jenis_tindakan === RiwayatKesehatan::JENIS_STERILISASI) {
                $this->kesehatan->pastikanSterilisasiTunggal($k->id_kucing, $catatan->id_kesehatan);
            }

            $catatan->save();
            $this->kesehatan->sinkronSteril($k, $catatan, $request->user());

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
}

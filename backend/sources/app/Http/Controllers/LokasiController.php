<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLokasiRequest;
use App\Http\Requests\UpdateLokasiRequest;
use App\Http\Resources\LokasiResource;
use App\Models\Kucing;
use App\Models\Lokasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Master lokasi persebaran kucing (dev-backend-03, MEOW-F-05, MEOW-F-38).
 * Baca: publik. Tambah/ubah/hapus: Admin Jaga Satwa (route `role:admin` + `audit:lokasi`).
 */
class LokasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = $request->validate([
            'id_kampus' => ['nullable', 'integer', Rule::exists('kampus', 'id_kampus')],
        ], [
            'id_kampus.integer' => 'Filter kampus harus berupa angka.',
            'id_kampus.exists'  => 'Kampus tidak ditemukan.',
        ]);

        $lokasi = $this->query()
            ->when($filter['id_kampus'] ?? null, fn (Builder $q, $idKampus) => $q->where('id_kampus', $idKampus))
            ->orderBy('id_kampus')
            ->orderBy('nama_lokasi')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => LokasiResource::collection($lokasi),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new LokasiResource($this->query()->findOrFail($id)),
        ]);
    }

    public function store(StoreLokasiRequest $request): JsonResponse
    {
        $lokasi = Lokasi::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lokasi berhasil ditambahkan.',
            'data'    => new LokasiResource($this->query()->find($lokasi->id_lokasi)),
        ], 201);
    }

    public function update(UpdateLokasiRequest $request, int $id): JsonResponse
    {
        $lokasi = Lokasi::findOrFail($id);
        $lokasi->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lokasi berhasil diperbarui.',
            'data'    => new LokasiResource($this->query()->find($lokasi->id_lokasi)),
        ]);
    }

    /**
     * Hapus hanya lokasi yang sudah tidak dipakai. Foreign key di basis data bersifat CASCADE,
     * jadi tanpa pengecekan ini menghapus lokasi ikut menghapus data kucing dan feeding log.
     * Baris lokasi dikunci (FOR UPDATE) agar tidak ada data baru yang menempel di tengah proses.
     */
    public function destroy(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $lokasi = Lokasi::whereKey($id)->lockForUpdate()->firstOrFail();

            $kucing = $lokasi->kucing()->count();
            $feeding = $lokasi->pemberianMakan()->count();

            if ($kucing > 0 || $feeding > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Lokasi tidak dapat dihapus karena masih dipakai oleh {$kucing} data kucing dan {$feeding} catatan pemberian makan. Pindahkan datanya ke lokasi lain terlebih dahulu.",
                    'data'    => ['jumlah_kucing' => $kucing, 'jumlah_pemberian_makan' => $feeding],
                ], 409);
            }

            $lokasi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Lokasi berhasil dihapus.',
                'data'    => ['id_lokasi' => $id],
            ]);
        });
    }

    private function query(): Builder
    {
        return Lokasi::query()
            ->with('kampus')
            ->withCount(['kucing' => fn (Builder $q) => $q->whereIn('status_kucing', Kucing::STATUS_DI_KAMPUS)]);
    }
}

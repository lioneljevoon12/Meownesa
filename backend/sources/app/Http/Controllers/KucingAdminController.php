<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterKucingAdminRequest;
use App\Http\Requests\StoreKucingRequest;
use App\Http\Requests\UpdateKucingRequest;
use App\Http\Resources\KucingAdminResource;
use App\Models\Kucing;
use App\Models\Lokasi;
use App\Services\FotoKucingService;
use App\Services\KodeKucingGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Pengelolaan data kucing oleh Admin Jaga Satwa (MEOW-F-03, F-04, MEOW-BR-01).
 * Route: role:admin + audit:kucing. Admin melihat SEMUA status, termasuk Hilang dan Almarhum.
 */
class KucingAdminController extends Controller
{
    public function __construct(
        private readonly KodeKucingGenerator $kode,
        private readonly FotoKucingService $foto,
    ) {
    }

    public function index(FilterKucingAdminRequest $request): JsonResponse
    {
        $filter = $request->validated();

        $halaman = Kucing::query()
            ->with(['lokasi.kampus', 'fotoUtama'])
            ->withCount('fotoKucing')
            ->filter($filter)
            ->paginate($filter['per_page'] ?? $request->perHalamanBawaan());

        return response()->json([
            'success' => true,
            'data'    => KucingAdminResource::collection($halaman->getCollection()),
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
        return response()->json(['success' => true, 'data' => new KucingAdminResource($this->detail($id))]);
    }

    public function store(StoreKucingRequest $request): JsonResponse
    {
        $data = $request->safe()->except('konfirmasi_sehat');
        $data['status_steril'] ??= 'Tidak Diketahui';
        $data['status_kucing'] ??= 'Terdata';

        $kucing = $this->buat($data);

        return response()->json([
            'success' => true,
            'message' => 'Data kucing berhasil ditambahkan dengan kode '.$kucing->kode_kucing.'.',
            'data'    => new KucingAdminResource($this->detail($kucing->id_kucing)),
        ], 201);
    }

    public function update(UpdateKucingRequest $request, int $id): JsonResponse
    {
        $kucing = Kucing::findOrFail($id);
        $kucing->update($request->safe()->except('konfirmasi_sehat'));

        return response()->json([
            'success' => true,
            'message' => 'Data kucing berhasil diperbarui.',
            'data'    => new KucingAdminResource($this->detail($kucing->id_kucing)),
        ]);
    }

    /**
     * Hapus permanen hanya untuk data yang salah input dan belum punya riwayat apa pun.
     * Foreign key bersifat CASCADE (riwayat kesehatan, pengajuan) / SET NULL (feeding), jadi
     * kucing yang sudah punya riwayat tidak boleh dihapus — ubah statusnya (Almarhum/Hilang).
     */
    public function destroy(int $id): JsonResponse
    {
        $berkas = [];

        $hasil = DB::transaction(function () use ($id, &$berkas) {
            $kucing = Kucing::whereKey($id)->lockForUpdate()->firstOrFail();

            $riwayat = $kucing->riwayatKesehatan()->count();
            $pengajuan = $kucing->pengajuanAdopsi()->count();
            $feeding = $kucing->pemberianMakan()->count();

            if ($riwayat + $pengajuan + $feeding > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Kucing tidak dapat dihapus karena sudah punya {$riwayat} riwayat kesehatan, {$pengajuan} pengajuan adopsi, dan {$feeding} catatan pemberian makan. "
                        .'Ubah statusnya menjadi Almarhum atau Hilang agar riwayatnya tetap tersimpan.',
                    'data'    => ['riwayat_kesehatan' => $riwayat, 'pengajuan_adopsi' => $pengajuan, 'pemberian_makan' => $feeding],
                ], 409);
            }

            $berkas = $kucing->fotoKucing()->pluck('url_foto')->all();
            $kucing->delete(); // foto_kucing ikut terhapus (CASCADE)

            return response()->json([
                'success' => true,
                'message' => 'Data kucing berhasil dihapus.',
                'data'    => ['id_kucing' => $id],
            ]);
        });

        foreach ($berkas as $url) {
            $this->foto->hapusBerkas($url);
        }

        return $hasil;
    }

    /** @param  array<string, mixed>  $data */
    private function buat(array $data): Kucing
    {
        $otomatis = empty($data['kode_kucing']);
        $lokasi = $otomatis ? Lokasi::with('kampus')->findOrFail($data['id_lokasi']) : null;

        // Kode otomatis dihitung dari kode terbesar; bila dua admin menambah bersamaan, ulangi.
        for ($percobaan = 1; ; $percobaan++) {
            if ($otomatis) {
                $data['kode_kucing'] = $this->kode->untuk($lokasi);
            }

            try {
                return Kucing::create($data);
            } catch (QueryException $e) {
                if (! $otomatis || $percobaan >= 3 || ! str_contains($e->getMessage(), 'kode_kucing')) {
                    throw $e;
                }
            }
        }
    }

    private function detail(int $id): Kucing
    {
        return Kucing::query()
            ->with(['lokasi.kampus', 'fotoUtama', 'fotoKucing' => fn ($relasi) => $relasi->orderByDesc('is_utama')->orderBy('id_foto_kucing')])
            ->withCount('fotoKucing')
            ->findOrFail($id);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterPascaAdopsiRequest;
use App\Http\Requests\StorePascaAdopsiRequest;
use App\Http\Requests\UpdatePascaAdopsiRequest;
use App\Http\Resources\PascaAdopsiResource;
use App\Models\PascaAdopsi;
use App\Models\SerahTerima;
use App\Services\PemantauanPascaAdopsiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Pemantauan kondisi kucing setelah diadopsi (MEOW-F-26, F-27, MEOW-BR-13). Route: role:admin +
 * audit:pasca_adopsi. Adopter memberi kabar lewat WhatsApp/CP; admin yang mencatatnya di sini.
 */
class PascaAdopsiController extends Controller
{
    public function __construct(private readonly PemantauanPascaAdopsiService $pemantauan)
    {
    }

    /** Daftar adopsi beserta status pemantauan dan jatuh tempo pembaruan berikutnya. */
    public function index(FilterPascaAdopsiRequest $request): JsonResponse
    {
        $f = $request->validated();
        $semua = $this->pemantauan->semua();

        // Jumlah per status tidak terpengaruh filter, untuk lencana tab di UI.
        $perStatus = array_fill_keys(PemantauanPascaAdopsiService::STATUS_PEMANTAUAN, 0);
        foreach ($semua->countBy('status_pemantauan') as $status => $jumlah) {
            $perStatus[$status] = $jumlah;
        }

        $baris = $semua
            ->when($f['status'] ?? null, fn (Collection $c, $v) => $c->where('status_pemantauan', $v))
            ->when($f['kondisi'] ?? null, fn (Collection $c, $v) => $c->where('kondisi_terkini', $v))
            ->when(trim((string) ($f['q'] ?? '')) !== '', function (Collection $c) use ($f) {
                $cari = mb_strtolower(trim((string) $f['q']));

                return $c->filter(fn (array $r) => str_contains(mb_strtolower(implode(' ', [
                    $r['kucing']['nama_kucing'] ?? '', $r['kucing']['kode_kucing'] ?? '',
                    $r['adopter']['nama'] ?? '', $r['adopter']['email'] ?? '', $r['adopter']['nomor_whatsapp'] ?? '',
                ])), $cari));
            });

        $baris = $this->urutkan($baris, $f['sort'] ?? 'prioritas');

        $perHalaman = $f['per_page'] ?? 20;
        $total = $baris->count();
        $halaman = max(1, (int) ($f['page'] ?? 1));

        return response()->json([
            'success' => true,
            'data'    => $baris->forPage($halaman, $perHalaman)->values(),
            'meta'    => [
                'halaman'           => $halaman,
                'halaman_akhir'     => max(1, (int) ceil($total / $perHalaman)),
                'per_halaman'       => $perHalaman,
                'total'             => $total,
                'jumlah_per_status' => $perStatus,
            ],
        ]);
    }

    /** Riwayat kondisi satu adopsi: yang terbaru di atas, beserta kondisi terkini dan jadwal berikutnya. */
    public function riwayat(int $idSerahTerima): JsonResponse
    {
        $ringkasan = $this->pemantauan->untuk($idSerahTerima);
        abort_if($ringkasan === null, 404, 'Data serah terima tidak ditemukan.');

        $riwayat = PascaAdopsi::where('id_serah_terima', $idSerahTerima)
            ->orderByDesc('tanggal_update')->orderByDesc('id_pasca_adopsi')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'pemantauan' => $ringkasan,
                'riwayat'    => PascaAdopsiResource::collection($riwayat)->resolve(),
            ],
        ]);
    }

    public function store(StorePascaAdopsiRequest $request): JsonResponse
    {
        $catatan = PascaAdopsi::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kondisi kucing berhasil dicatat.',
            'data'    => (new PascaAdopsiResource($catatan))->resolve() + ['pemantauan' => $this->pemantauan->untuk($catatan->id_serah_terima)],
        ], 201);
    }

    public function update(UpdatePascaAdopsiRequest $request, int $id): JsonResponse
    {
        $catatan = PascaAdopsi::findOrFail($id);
        $catatan->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Catatan kondisi berhasil diperbarui.',
            'data'    => (new PascaAdopsiResource($catatan->refresh()))->resolve() + ['pemantauan' => $this->pemantauan->untuk($catatan->id_serah_terima)],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $catatan = PascaAdopsi::findOrFail($id);
        $catatan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catatan kondisi berhasil dihapus.',
            'data'    => ['id_pasca_adopsi' => $id, 'id_serah_terima' => $catatan->id_serah_terima],
        ]);
    }

    /**
     * prioritas: yang terlambat paling lama dulu, lalu yang akan jatuh tempo, lalu yang sudah selesai/berakhir.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     */
    private function urutkan(Collection $baris, string $sort): Collection
    {
        return match ($sort) {
            'terbaru' => $baris->sortByDesc('tanggal_serah_terima')->values(),
            'terlama' => $baris->sortBy('tanggal_serah_terima')->values(),
            default   => $baris->sort(function (array $a, array $b) {
                $urut = ['terlambat' => 0, 'aktif' => 1, 'selesai' => 2, 'berakhir' => 3];

                return [$urut[$a['status_pemantauan']], $a['status_pemantauan'] === 'terlambat' ? -$a['terlambat_hari'] : 0, $a['jatuh_tempo'], $b['tanggal_serah_terima']]
                    <=> [$urut[$b['status_pemantauan']], $b['status_pemantauan'] === 'terlambat' ? -$b['terlambat_hari'] : 0, $b['jatuh_tempo'], $a['tanggal_serah_terima']];
            })->values(),
        };
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RecordAuditLog;
use App\Http\Requests\FilterPemberianMakanRequest;
use App\Http\Requests\RekapPemberianMakanRequest;
use App\Http\Requests\StorePemberianMakanRequest;
use App\Http\Requests\UpdatePemberianMakanRequest;
use App\Http\Resources\PemberianMakanResource;
use App\Models\Lokasi;
use App\Models\PemberianMakan;
use App\Services\FeedingService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Feeding log oleh Admin Jaga Satwa (MEOW-F-07 s/d F-09), menggantikan spreadsheet presensi.
 * Route: role:admin + audit:pemberian_makan. Tidak ada endpoint publik: log feeding data internal.
 */
class PemberianMakanController extends Controller
{
    public function __construct(private readonly FeedingService $feeding)
    {
    }

    public function index(FilterPemberianMakanRequest $request): JsonResponse
    {
        $f = $request->validated();

        $halaman = $this->terapkanFilter(PemberianMakan::query()->with(['lokasi.kampus', 'kucing']), $f)
            ->orderByDesc('tanggal')
            ->orderByDesc('id_pemberian')
            ->paginate($f['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data'    => PemberianMakanResource::collection($halaman->getCollection()),
            'meta'    => [
                'halaman'       => $halaman->currentPage(),
                'halaman_akhir' => $halaman->lastPage(),
                'per_halaman'   => $halaman->perPage(),
                'total'         => $halaman->total(),
            ],
        ]);
    }

    /** Ringkasan statistik satu periode (minggu ISO Senin-Minggu atau bulan kalender) yang memuat `tanggal`. */
    public function rekap(RekapPemberianMakanRequest $request): JsonResponse
    {
        $f = $request->validated();
        $periode = $f['periode'] ?? 'mingguan';
        $acuan = Carbon::parse($f['tanggal'] ?? Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString());

        [$dari, $sampai] = $periode === 'bulanan'
            ? [$acuan->copy()->startOfMonth(), $acuan->copy()->endOfMonth()]
            : [$acuan->copy()->startOfWeek(CarbonInterface::MONDAY), $acuan->copy()->endOfWeek(CarbonInterface::SUNDAY)];

        // `tanggal` di sini hanya acuan periode, bukan filter satu hari (beda dengan daftar feeding).
        $baris = $this->terapkanFilter(PemberianMakan::query(), Arr::except($f, ['tanggal']))
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw('tanggal, id_lokasi, status_pemberian, COUNT(*) as jumlah')
            ->groupBy('tanggal', 'id_lokasi', 'status_pemberian')
            ->toBase()
            ->get();

        $hitung = fn ($koleksi) => [
            'tuntas'    => (int) $koleksi->where('status_pemberian', 'Tuntas')->sum('jumlah'),
            'belum'     => (int) $koleksi->where('status_pemberian', 'Belum')->sum('jumlah'),
            'terjadwal' => (int) $koleksi->where('status_pemberian', 'Terjadwal')->sum('jumlah'),
        ];
        $persen = fn (array $h) => ($h['tuntas'] + $h['belum']) > 0
            ? round($h['tuntas'] / ($h['tuntas'] + $h['belum']) * 100, 1)
            : null;

        $total = $hitung($baris);

        // Seluruh hari dalam periode ditampilkan (nol bila tidak ada catatan) agar siap digambar sebagai grafik.
        $perHari = [];
        for ($hari = $dari->copy(); $hari->lte($sampai); $hari->addDay()) {
            $tgl = $hari->toDateString();
            $perHari[] = ['tanggal' => $tgl] + $hitung($baris->filter(fn ($b) => substr((string) $b->tanggal, 0, 10) === $tgl));
        }

        $namaLokasi = Lokasi::with('kampus')->whereIn('id_lokasi', $baris->pluck('id_lokasi')->unique())->get()->keyBy('id_lokasi');
        $perLokasi = $baris->groupBy('id_lokasi')->map(function ($grup, $idLokasi) use ($hitung, $persen, $namaLokasi) {
            $h = $hitung($grup);

            return [
                'id_lokasi'    => (int) $idLokasi,
                'nama_lokasi'  => $namaLokasi[$idLokasi]->nama_lokasi ?? null,
                'nama_kampus'  => $namaLokasi[$idLokasi]->kampus->nama_kampus ?? null,
            ] + $h + ['total' => array_sum($h), 'persen_tuntas' => $persen($h)];
        })->sortBy('nama_lokasi')->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'periode'    => $periode,
                'dari'       => $dari->toDateString(),
                'sampai'     => $sampai->toDateString(),
                'ringkasan'  => $total + ['total' => array_sum($total), 'persen_tuntas' => $persen($total)],
                'per_lokasi' => $perLokasi,
                'per_hari'   => $perHari,
            ],
        ]);
    }

    public function store(StorePemberianMakanRequest $request): JsonResponse
    {
        [$catatan, $dibuat] = $this->feeding->simpan($request->validated());

        if (! $dibuat) {
            // Pengiriman ulang: tidak ada data baru, jadi jangan dicatat sebagai CREATE di audit log.
            // Memakai request() (instans yang dilihat middleware); $request di sini salinan FormRequest.
            request()->attributes->set(RecordAuditLog::LEWATI, true);
        }

        return response()->json([
            'success' => true,
            'message' => $dibuat ? 'Catatan pemberian makan berhasil ditambahkan.' : 'Catatan ini sudah tersimpan sebelumnya (pengiriman ulang diabaikan).',
            'data'    => new PemberianMakanResource($catatan->load(['lokasi.kampus', 'kucing'])),
        ], $dibuat ? 201 : 200);
    }

    /**
     * Sinkronisasi massal dari HP: satu permintaan berisi hingga 100 catatan, tiap catatan diproses
     * mandiri. Respons 200 dengan hasil per catatan: dibuat | sudah_ada (UUID pernah dikirim) | ditolak.
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'entries'   => ['required', 'array', 'min:1', 'max:100'],
            'entries.*' => ['array'],
        ], [
            'entries.required' => 'Daftar catatan (entries) wajib diisi.',
            'entries.array'    => 'entries harus berupa daftar catatan.',
            'entries.min'      => 'Minimal 1 catatan per permintaan.',
            'entries.max'      => 'Maksimal 100 catatan per permintaan. Kirim dalam beberapa batch.',
            'entries.*.array'  => 'Setiap catatan harus berupa objek.',
        ]);

        $hasil = [];
        $ringkasan = ['dibuat' => 0, 'sudah_ada' => 0, 'ditolak' => 0];

        foreach ($request->input('entries') as $i => $mentah) {
            $item = ['index' => $i, 'client_uuid' => $mentah['client_uuid'] ?? null];

            $validator = Validator::make($mentah, $this->feeding->aturan(sebagian: false));
            $galat = $validator->fails() ? $validator->errors()->toArray() : [];

            $data = [];
            if (! $galat) {
                $data = $this->feeding->lengkapi($validator->validated() + ['id_kucing' => null]);
                foreach ($this->feeding->periksa($data) as $field => $pesan) {
                    $galat[$field] = [$pesan];
                }
            }

            if (! $galat) {
                try {
                    [$catatan, $dibuat] = $this->feeding->simpan($data);
                    $item += ['status' => $dibuat ? 'dibuat' : 'sudah_ada', 'id_pemberian' => $catatan->id_pemberian];
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

        return response()->json([
            'success' => true,
            'message' => "{$ringkasan['dibuat']} dibuat, {$ringkasan['sudah_ada']} sudah ada, {$ringkasan['ditolak']} ditolak.",
            'data'    => ['ringkasan' => $ringkasan, 'hasil' => $hasil],
        ]);
    }

    public function update(UpdatePemberianMakanRequest $request, int $id): JsonResponse
    {
        $catatan = PemberianMakan::findOrFail($id);
        $catatan->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Catatan pemberian makan berhasil diperbarui.',
            'data'    => new PemberianMakanResource($catatan->refresh()->load(['lokasi.kampus', 'kucing'])),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        PemberianMakan::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catatan pemberian makan berhasil dihapus.',
            'data'    => ['id_pemberian' => $id],
        ]);
    }

    /** @param  array<string, mixed>  $f */
    private function terapkanFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['tanggal'] ?? null, fn (Builder $q, $v) => $q->where('tanggal', $v))
            ->when($f['dari'] ?? null, fn (Builder $q, $v) => $q->where('tanggal', '>=', $v))
            ->when($f['sampai'] ?? null, fn (Builder $q, $v) => $q->where('tanggal', '<=', $v))
            ->when($f['id_lokasi'] ?? null, fn (Builder $q, $v) => $q->where('id_lokasi', $v))
            ->when($f['id_kampus'] ?? null, fn (Builder $q, $v) => $q->whereHas('lokasi', fn ($l) => $l->where('id_kampus', $v)))
            ->when($f['id_kucing'] ?? null, fn (Builder $q, $v) => $q->where('id_kucing', $v))
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status_pemberian', $v));
    }
}

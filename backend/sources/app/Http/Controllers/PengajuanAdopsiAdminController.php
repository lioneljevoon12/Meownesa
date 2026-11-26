<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterPengajuanAdminRequest;
use App\Http\Requests\UbahStatusPengajuanRequest;
use App\Http\Resources\PengajuanAdminDetailResource;
use App\Http\Resources\PengajuanAdminResource;
use App\Models\PengajuanAdopsi;
use App\Services\AuditLogger;
use App\Services\LampiranService;
use App\Services\PengajuanStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Antrean dan keputusan pengajuan adopsi oleh Admin Jaga Satwa (MEOW-F-16, F-19, MEOW-BR-05, BR-08).
 * Route: role:admin. Super Admin sengaja tidak punya akses: keputusan adopsi bukan wewenang teknis.
 */
class PengajuanAdopsiAdminController extends Controller
{
    public function __construct(private readonly PengajuanStatusService $status)
    {
    }

    public function index(FilterPengajuanAdminRequest $request): JsonResponse
    {
        $f = $request->validated();

        $query = PengajuanAdopsi::query()
            ->with(['kucing.fotoUtama', 'screeningAdopsi'])
            ->withCount('lampiranPengajuan')
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status_pengajuan', $v))
            ->when($f['id_kucing'] ?? null, fn (Builder $q, $v) => $q->where('id_kucing', $v))
            ->when($f['dari'] ?? null, fn (Builder $q, $v) => $q->where('tanggal_pengajuan', '>=', $v))
            ->when($f['sampai'] ?? null, fn (Builder $q, $v) => $q->where('tanggal_pengajuan', '<=', $v))
            ->when(trim((string) ($f['q'] ?? '')) !== '', function (Builder $q) use ($f) {
                $like = '%'.addcslashes(trim((string) $f['q']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->where('nomor_pengajuan', 'like', $like)
                    ->orWhere('nama_pemohon', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('nomor_whatsapp', 'like', $like)
                    ->orWhere('kode_tracking', 'like', $like));
            });

        // Antrean: yang paling lama menunggu di atas (adil bagi pemohon); `terbaru` untuk melihat yang masuk terakhir.
        ($f['sort'] ?? 'terlama') === 'terbaru'
            ? $query->orderByDesc('tanggal_pengajuan')->orderByDesc('id_pengajuan')
            : $query->orderBy('tanggal_pengajuan')->orderBy('id_pengajuan');

        $halaman = $query->paginate($f['per_page'] ?? 20);

        // Jumlah per status (tidak terpengaruh filter status) untuk lencana tab antrean di UI.
        $perStatus = array_fill_keys(PengajuanAdopsi::STATUS, 0);
        foreach (PengajuanAdopsi::selectRaw('status_pengajuan, COUNT(*) as jumlah')->groupBy('status_pengajuan')->toBase()->get() as $baris) {
            $perStatus[$baris->status_pengajuan] = (int) $baris->jumlah;
        }

        return response()->json([
            'success' => true,
            'data'    => PengajuanAdminResource::collection($halaman->getCollection()),
            'meta'    => [
                'halaman'          => $halaman->currentPage(),
                'halaman_akhir'    => $halaman->lastPage(),
                'per_halaman'      => $halaman->perPage(),
                'total'            => $halaman->total(),
                'jumlah_per_status' => $perStatus,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new PengajuanAdminDetailResource($this->detail($id))]);
    }

    public function ubahStatus(UbahStatusPengajuanRequest $request, int $id): JsonResponse
    {
        $this->status->ubah($id, $request->input('status'), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diubah menjadi '.$request->input('status').'.',
            'data'    => new PengajuanAdminDetailResource($this->detail($id)),
        ]);
    }

    /**
     * Unduh/lihat satu lampiran (KTP, foto rumah, dokumen). Hanya admin; setiap akses dicatat di audit log
     * karena ini data pribadi (MEOW-NF-04). Berkas tidak pernah punya URL publik.
     */
    public function lampiran(Request $request, int $id, int $lampiran): StreamedResponse
    {
        $pengajuan = PengajuanAdopsi::findOrFail($id);
        $berkas = $pengajuan->lampiranPengajuan()->whereKey($lampiran)->firstOrFail();

        abort_unless(Storage::disk(LampiranService::DISK)->exists($berkas->url_file), 404, 'Berkas lampiran tidak ditemukan di penyimpanan.');

        AuditLogger::record($request->user(), 'LIHAT_LAMPIRAN', 'lampiran_pengajuan', $berkas->id_lampiran);

        $ekstensi = pathinfo($berkas->url_file, PATHINFO_EXTENSION);
        $nama = $pengajuan->nomor_pengajuan.'-'.Str::slug($berkas->jenis_lampiran).'.'.$ekstensi;

        return Storage::disk(LampiranService::DISK)->response(
            $berkas->url_file,
            $nama,
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
            // gambar boleh dipratinjau; PDF dari pihak luar selalu diunduh, tidak dibuka di peramban
            $ekstensi === 'pdf' ? 'attachment' : 'inline',
        );
    }

    private function detail(int $id): PengajuanAdopsi
    {
        return PengajuanAdopsi::query()
            ->with(['kucing.fotoUtama', 'lampiranPengajuan', 'screeningAdopsi.admin', 'notifikasi'])
            ->findOrFail($id);
    }
}

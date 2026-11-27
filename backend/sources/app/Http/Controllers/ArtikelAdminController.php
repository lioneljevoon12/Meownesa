<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RecordAuditLog;
use App\Http\Requests\FilterArtikelRequest;
use App\Http\Requests\StoreArtikelRequest;
use App\Http\Requests\UpdateArtikelRequest;
use App\Http\Requests\UploadThumbnailArtikelRequest;
use App\Http\Resources\ArtikelAdminResource;
use App\Models\Artikel;
use App\Services\ArtikelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * CMS artikel untuk Admin Jaga Satwa (MEOW-F-33 s/d F-35). Route: role:admin + audit:artikel.
 * Mencakup artikel edukasi, informasi kegiatan, dan kampanye kalung (lewat kategori).
 */
class ArtikelAdminController extends Controller
{
    public function __construct(private readonly ArtikelService $service)
    {
    }

    /** Semua status, terbaru diubah di atas. `meta.jumlah_per_status` untuk lencana tab. */
    public function index(FilterArtikelRequest $request): JsonResponse
    {
        $f = $request->validated();

        $halaman = Artikel::with('author:id_user,nama')
            ->filter($f)
            ->orderByDesc('updated_at')->orderByDesc('id_artikel')
            ->paginate($f['per_page'] ?? 20, ['*'], 'page', $f['page'] ?? 1);

        // Jumlah per status mengikuti filter lain, tetapi bukan filter status itu sendiri.
        $perStatus = array_fill_keys(Artikel::STATUS, 0);
        $hitung = Artikel::filter(array_diff_key($f, ['status' => 1]))
            ->selectRaw('status_publikasi, count(*) as jumlah')->groupBy('status_publikasi')->pluck('jumlah', 'status_publikasi');
        foreach ($hitung as $status => $jumlah) {
            $perStatus[$status] = (int) $jumlah;
        }

        return response()->json([
            'success' => true,
            'data'    => ArtikelAdminResource::collection($halaman->getCollection())->resolve(),
            'meta'    => [
                'halaman'           => $halaman->currentPage(),
                'halaman_akhir'     => $halaman->lastPage(),
                'per_halaman'       => $halaman->perPage(),
                'total'             => $halaman->total(),
                'jumlah_per_status' => $perStatus,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $artikel = Artikel::with('author:id_user,nama')->findOrFail($id);

        return response()->json(['success' => true, 'data' => ArtikelAdminResource::detail($artikel)->resolve()]);
    }

    public function store(StoreArtikelRequest $request): JsonResponse
    {
        $d = $request->validated();
        $path = null;

        try {
            $artikel = DB::transaction(function () use ($request, $d, &$path) {
                $artikel = new Artikel([
                    'id_user'        => $request->user()->id_user,
                    'judul'          => $d['judul'],
                    'slug'           => ($d['slug'] ?? null) ?: $this->service->buatSlug($d['judul']),
                    'kategori'       => $d['kategori'],
                    'isi'            => $d['isi'],
                    'tanggal_dibuat' => $this->service->hariIni(),
                    'is_featured'    => (bool) ($d['is_featured'] ?? false),
                    'is_pinned'      => (bool) ($d['is_pinned'] ?? false),
                ]);
                $this->service->terapkanStatus($artikel, $d['status_publikasi'] ?? Artikel::STATUS_DRAFT);

                if ($request->hasFile('thumbnail')) {
                    $path = $this->simpanThumbnail($request->file('thumbnail'));
                    $artikel->thumbnail_url = $path;
                }

                $artikel->save();

                return $artikel;
            });
        } catch (Throwable $e) {
            $this->service->hapusThumbnail($path);   // jangan tinggalkan berkas yatim bila penyimpanan gagal
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil dibuat.',
            'data'    => ArtikelAdminResource::detail($artikel->load('author:id_user,nama'))->resolve(),
        ], 201);
    }

    public function update(UpdateArtikelRequest $request, int $id): JsonResponse
    {
        $d = $request->validated();
        $artikel = Artikel::findOrFail($id);

        foreach (['judul', 'kategori', 'isi'] as $kolom) {
            if (array_key_exists($kolom, $d)) {
                $artikel->{$kolom} = $d[$kolom];
            }
        }
        if (! empty($d['slug'])) {
            $artikel->slug = $d['slug'];
        }
        if (isset($d['status_publikasi'])) {
            $this->service->terapkanStatus($artikel, $d['status_publikasi']);
        }
        // Validasi sudah memastikan sematan/unggulan hanya diminta untuk artikel yang berstatus PUBLISHED.
        foreach (['is_featured', 'is_pinned'] as $kolom) {
            if (array_key_exists($kolom, $d) && $artikel->status_publikasi === Artikel::STATUS_PUBLISHED) {
                $artikel->{$kolom} = (bool) $d[$kolom];
            }
        }
        $artikel->save();

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil diperbarui.',
            'data'    => ArtikelAdminResource::detail($artikel->refresh()->load('author:id_user,nama'))->resolve(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $artikel = Artikel::findOrFail($id);
        $thumbnail = $artikel->thumbnail_url;
        $artikel->delete();
        $this->service->hapusThumbnail($thumbnail);

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil dihapus.',
            'data'    => ['id_artikel' => $id],
        ]);
    }

    /** Unggah atau ganti thumbnail. Berkas lama (bila hasil unggahan) ikut dihapus. */
    public function uploadThumbnail(UploadThumbnailArtikelRequest $request, int $id): JsonResponse
    {
        $artikel = Artikel::findOrFail($id);
        $lama = $artikel->thumbnail_url;
        $baru = $this->simpanThumbnail($request->file('thumbnail'));

        try {
            $artikel->update(['thumbnail_url' => $baru]);
        } catch (Throwable $e) {
            $this->service->hapusThumbnail($baru);
            throw $e;
        }
        $this->service->hapusThumbnail($lama);

        return response()->json([
            'success' => true,
            'message' => 'Thumbnail berhasil diunggah.',
            'data'    => ArtikelAdminResource::detail($artikel->load('author:id_user,nama'))->resolve(),
        ], 201);
    }

    public function hapusThumbnail(Request $request, int $id): JsonResponse
    {
        $artikel = Artikel::findOrFail($id);

        if ($artikel->thumbnail_url === null) {
            $request->attributes->set(RecordAuditLog::LEWATI, true);   // tidak ada yang berubah

            return response()->json([
                'success' => true,
                'message' => 'Artikel ini memang belum punya thumbnail.',
                'data'    => ArtikelAdminResource::detail($artikel->load('author:id_user,nama'))->resolve(),
            ]);
        }

        $lama = $artikel->thumbnail_url;
        $artikel->update(['thumbnail_url' => null]);
        $this->service->hapusThumbnail($lama);

        return response()->json([
            'success' => true,
            'message' => 'Thumbnail berhasil dihapus.',
            'data'    => ArtikelAdminResource::detail($artikel->load('author:id_user,nama'))->resolve(),
        ]);
    }

    private function simpanThumbnail($berkas): string
    {
        try {
            return $this->service->simpanThumbnail($berkas);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['thumbnail' => $e->getMessage()]);
        }
    }
}

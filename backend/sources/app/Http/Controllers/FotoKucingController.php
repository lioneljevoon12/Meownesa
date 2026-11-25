<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFotoKucingRequest;
use App\Http\Resources\FotoKucingResource;
use App\Models\FotoKucing;
use App\Models\Kucing;
use App\Services\FotoKucingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Galeri multi-foto kucing (dev-backend-04). Route: role:admin + audit:foto_kucing.
 * Invarian: kucing yang punya foto selalu punya TEPAT SATU foto utama (is_utama = true).
 * Setiap perubahan dilakukan dalam transaksi dengan baris kucing dikunci (FOR UPDATE)
 * agar dua permintaan bersamaan tidak menghasilkan dua foto utama.
 */
class FotoKucingController extends Controller
{
    public const MAKS_FOTO = 10;

    public function __construct(private readonly FotoKucingService $service)
    {
    }

    public function store(UploadFotoKucingRequest $request, int $kucing): JsonResponse
    {
        $path = null;

        try {
            $foto = DB::transaction(function () use ($request, $kucing, &$path) {
                $k = Kucing::whereKey($kucing)->lockForUpdate()->firstOrFail();

                if ($k->fotoKucing()->count() >= self::MAKS_FOTO) {
                    throw ValidationException::withMessages([
                        'foto' => 'Satu kucing maksimal '.self::MAKS_FOTO.' foto. Hapus foto lama terlebih dahulu.',
                    ]);
                }

                try {
                    $path = $this->service->simpan($k, $request->file('foto'));
                } catch (RuntimeException $e) {
                    throw ValidationException::withMessages(['foto' => $e->getMessage()]);
                }

                return FotoKucing::create([
                    'id_kucing' => $k->id_kucing,
                    'url_foto'  => $path,
                    // foto pertama otomatis menjadi foto utama
                    'is_utama'  => ! $k->fotoKucing()->where('is_utama', true)->exists(),
                ]);
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                $this->service->hapusBerkas($path); // jangan tinggalkan berkas yatim bila transaksi gagal
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil diunggah.',
            'data'    => new FotoKucingResource($foto),
        ], 201);
    }

    public function setUtama(int $kucing, int $foto): JsonResponse
    {
        return DB::transaction(function () use ($kucing, $foto) {
            $k = Kucing::whereKey($kucing)->lockForUpdate()->firstOrFail();
            $target = $k->fotoKucing()->whereKey($foto)->firstOrFail();

            // Lepas status utama dari foto LAIN saja. Jangan lepas dari target lalu "update" target:
            // Eloquent melewati UPDATE bila atribut di memori sudah true, sehingga foto utama bisa hilang.
            $k->fotoKucing()->whereKeyNot($foto)->where('is_utama', true)->update(['is_utama' => false]);
            if (! $target->is_utama) {
                $target->update(['is_utama' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Foto utama berhasil diubah.',
                'data'    => $this->ringkasan($k, $target->id_foto_kucing),
            ]);
        });
    }

    public function destroy(int $kucing, int $foto): JsonResponse
    {
        $url = null;

        $respons = DB::transaction(function () use ($kucing, $foto, &$url) {
            $k = Kucing::whereKey($kucing)->lockForUpdate()->firstOrFail();
            $target = $k->fotoKucing()->whereKey($foto)->firstOrFail();

            $url = $target->url_foto;
            $targetUtama = $target->is_utama;
            $target->delete();

            // Foto utama dihapus: jadikan foto tertua yang tersisa sebagai foto utama baru.
            if ($targetUtama) {
                $k->fotoKucing()->orderBy('id_foto_kucing')->first()?->update(['is_utama' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil dihapus.',
                'data'    => $this->ringkasan($k, $foto),
            ]);
        });

        $this->service->hapusBerkas($url);

        return $respons;
    }

    /** id_foto_kucing harus kunci pertama: dipakai middleware audit sebagai id_data. */
    private function ringkasan(Kucing $k, int $idFoto): array
    {
        return [
            'id_foto_kucing' => $idFoto,
            'id_kucing'      => $k->id_kucing,
            'galeri'         => FotoKucingResource::collection(
                $k->fotoKucing()->orderByDesc('is_utama')->orderBy('id_foto_kucing')->get()
            )->resolve(),
        ];
    }
}

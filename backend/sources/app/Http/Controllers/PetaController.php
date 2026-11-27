<?php

namespace App\Http\Controllers;

use App\Models\Kampus;
use App\Models\Kucing;
use App\Models\Lokasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Peta persebaran kucing (MEOW-F-38, F-39) untuk publik.
 *
 * Titik peta adalah LOKASI umum kampus (gedung/area), bukan posisi tiap kucing, dan hanya memuat jumlah
 * kucing yang memang tampil di katalog publik. Tidak pernah ada data adopter, alamat, atau kucing yang
 * sedang diproses (MEOW-NF-04 s/d NF-06). Lokasi tanpa koordinat valid (0,0 = belum diisi) tidak dipetakan.
 */
class PetaController extends Controller
{
    public function persebaran(Request $request): JsonResponse
    {
        $f = $request->validate([
            'id_kampus' => ['nullable', 'integer', Rule::exists('kampus', 'id_kampus')],
            'format'    => ['nullable', Rule::in(['titik', 'geojson'])],
        ], [
            'id_kampus.integer' => 'Filter kampus harus berupa angka.',
            'id_kampus.exists'  => 'Kampus tidak ditemukan.',
            'format.in'         => 'Format harus titik atau geojson.',
        ]);

        $lokasi = Lokasi::query()
            ->with('kampus:id_kampus,nama_kampus')
            ->when($f['id_kampus'] ?? null, fn ($q, $v) => $q->where('id_kampus', $v))
            ->withCount([
                'kucing as jumlah_kucing'      => fn ($q) => $q->whereIn('status_kucing', Kucing::STATUS_PUBLIK),
                'kucing as jumlah_siap_adopsi' => fn ($q) => $q->where('status_kucing', 'Tersedia'),
            ])
            ->orderBy('id_kampus')->orderBy('nama_lokasi')
            ->get();

        $valid = $lokasi->filter(fn (Lokasi $l) => $this->koordinatValid($l));

        $titik = $valid->map(fn (Lokasi $l) => [
            'id_lokasi'           => $l->id_lokasi,
            'nama_lokasi'         => $l->nama_lokasi,
            'id_kampus'           => $l->id_kampus,
            'nama_kampus'         => $l->kampus?->nama_kampus,
            'lat'                 => $l->koordinat_x,
            'lng'                 => $l->koordinat_y,
            'jumlah_kucing'       => (int) $l->jumlah_kucing,
            'jumlah_siap_adopsi'  => (int) $l->jumlah_siap_adopsi,
        ])->values();

        $meta = ['total_titik' => $titik->count(), 'tanpa_koordinat' => $lokasi->count() - $titik->count()];

        $data = ($f['format'] ?? 'titik') === 'geojson'
            ? $this->geojson($titik->all())
            : ['titik' => $titik->all(), 'kampus' => $this->ringkasKampus($lokasi, $valid, $f['id_kampus'] ?? null)];

        return response()->json(['success' => true, 'data' => $data, 'meta' => $meta])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /** Koordinat terisi (bukan bawaan 0,0) dan berada dalam rentang lintang/bujur yang sah. */
    private function koordinatValid(Lokasi $l): bool
    {
        $lat = (float) $l->koordinat_x;
        $lng = (float) $l->koordinat_y;

        return ! ($lat == 0.0 && $lng == 0.0) && abs($lat) <= 90 && abs($lng) <= 180;
    }

    /**
     * Ringkasan per kampus beserta titik tengah (rata-rata titik valid) untuk memusatkan peta.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ringkasKampus($semua, $valid, ?int $idKampus): array
    {
        return Kampus::query()
            ->when($idKampus, fn ($q, $v) => $q->where('id_kampus', $v))
            ->orderBy('id_kampus')->get()
            ->map(function (Kampus $k) use ($semua, $valid) {
                $titik = $valid->where('id_kampus', $k->id_kampus);

                return [
                    'id_kampus'     => $k->id_kampus,
                    'nama_kampus'   => $k->nama_kampus,
                    'pusat'         => $titik->isEmpty() ? null : [
                        'lat' => round($titik->avg('koordinat_x'), 8),
                        'lng' => round($titik->avg('koordinat_y'), 8),
                    ],
                    'jumlah_lokasi' => $semua->where('id_kampus', $k->id_kampus)->count(),
                    'jumlah_kucing' => (int) $semua->where('id_kampus', $k->id_kampus)->sum('jumlah_kucing'),
                ];
            })->values()->all();
    }

    /**
     * GeoJSON FeatureCollection. Ingat: GeoJSON memakai urutan [bujur, lintang], kebalikan Leaflet [lat, lng].
     *
     * @param  array<int, array<string, mixed>>  $titik
     * @return array<string, mixed>
     */
    private function geojson(array $titik): array
    {
        return [
            'type'     => 'FeatureCollection',
            'features' => array_map(fn (array $t) => [
                'type'       => 'Feature',
                'geometry'   => ['type' => 'Point', 'coordinates' => [$t['lng'], $t['lat']]],
                'properties' => array_diff_key($t, ['lat' => 1, 'lng' => 1]),
            ], $titik),
        ];
    }
}

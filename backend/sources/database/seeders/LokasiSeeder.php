<?php

namespace Database\Seeders;

use App\Models\Kampus;
use App\Models\Lokasi;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Lokasi aktual dari spreadsheet presensi Jaga Satwa (1 sheet = 1 titik feeding).
 * Sumber nama lokasi: database/seeders/data/kucing_aktual.json.
 *
 * KOORDINAT: spreadsheet tidak memuat koordinat, jadi dicari dari Google Maps/OpenStreetMap dan disimpan di
 * database/seeders/data/koordinat_lokasi.json (lengkap dengan asal dan tingkat akurasinya). Akurasinya tingkat
 * gedung/area; yang berakurasi "perkiraan" perlu dicek pengurus. Koreksi lewat PUT /api/admin/lokasi/{id}.
 *
 * Aman dijalankan ulang: lokasi yang sudah ada dan MASIH memakai titik tengah kampus bawaan lama diperbarui ke
 * koordinat sebenarnya, tetapi koordinat yang sudah diubah admin tidak pernah ditimpa.
 */
class LokasiSeeder extends Seeder
{
    /** Selisih (derajat) di bawah ini dianggap sama dengan titik tengah kampus bawaan lama. */
    private const TOLERANSI = 0.000001;

    public function run(): void
    {
        $koordinat = self::koordinat();

        foreach (self::data()['kampus'] as $kampusData) {
            $kampus = Kampus::where('nama_kampus', $kampusData['nama_kampus'])->first()
                ?? throw new RuntimeException("Kampus '{$kampusData['nama_kampus']}' belum ada. Jalankan KampusSeeder dulu.");

            [$latDefault, $lngDefault] = $kampusData['koordinat_default'];

            foreach ($kampusData['lokasi'] as $lok) {
                $titik = $koordinat[$kampusData['nama_kampus']][$lok['nama_lokasi']] ?? null;
                $lat = $titik['lat'] ?? $latDefault;
                $lng = $titik['lng'] ?? $lngDefault;

                $lokasi = Lokasi::firstOrCreate(
                    ['id_kampus' => $kampus->id_kampus, 'nama_lokasi' => $lok['nama_lokasi']],
                    ['koordinat_x' => $lat, 'koordinat_y' => $lng]
                );

                // Data lama yang masih di titik tengah kampus -> pindahkan ke titik sebenarnya.
                if ($titik && ! $lokasi->wasRecentlyCreated
                    && abs($lokasi->koordinat_x - $latDefault) < self::TOLERANSI && abs($lokasi->koordinat_y - $lngDefault) < self::TOLERANSI) {
                    $lokasi->update(['koordinat_x' => $lat, 'koordinat_y' => $lng]);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    public static function data(): array
    {
        static $data = null;

        return $data ??= json_decode(
            file_get_contents(database_path('seeders/data/kucing_aktual.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    /**
     * Koordinat per kampus per lokasi: [nama_kampus => [nama_lokasi => ['lat', 'lng', 'akurasi', 'acuan']]].
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function koordinat(): array
    {
        static $data = null;

        return $data ??= json_decode(
            file_get_contents(database_path('seeders/data/koordinat_lokasi.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        )['kampus'];
    }
}

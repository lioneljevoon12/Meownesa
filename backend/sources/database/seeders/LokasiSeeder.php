<?php

namespace Database\Seeders;

use App\Models\Kampus;
use App\Models\Lokasi;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Lokasi aktual dari spreadsheet presensi Jaga Satwa (1 sheet = 1 titik feeding).
 * Sumber: database/seeders/data/kucing_aktual.json.
 *
 * KOORDINAT SEMENTARA: spreadsheet tidak memuat koordinat, jadi semua lokasi memakai titik
 * tengah kampus. Admin wajib mengoreksi lewat PUT /api/admin/lokasi/{id}.
 */
class LokasiSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::data()['kampus'] as $kampusData) {
            $kampus = Kampus::where('nama_kampus', $kampusData['nama_kampus'])->first()
                ?? throw new RuntimeException("Kampus '{$kampusData['nama_kampus']}' belum ada. Jalankan KampusSeeder dulu.");

            [$lat, $lng] = $kampusData['koordinat_default'];

            foreach ($kampusData['lokasi'] as $lok) {
                Lokasi::firstOrCreate(
                    ['id_kampus' => $kampus->id_kampus, 'nama_lokasi' => $lok['nama_lokasi']],
                    ['koordinat_x' => $lat, 'koordinat_y' => $lng]
                );
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
}

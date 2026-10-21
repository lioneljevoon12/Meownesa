<?php

namespace Database\Seeders;

use App\Models\Lokasi;
use Illuminate\Database\Seeder;

class LokasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lokasiData = [
            // Kampus 1: Ketintang
            [
                'id_kampus' => 1,
                'nama_lokasi' => 'Gedung K10 & K4 — Fakultas Vokasi',
                'koordinat_x' => -7.31290000,
                'koordinat_y' => 112.72750000,
            ],
            [
                'id_kampus' => 1,
                'nama_lokasi' => 'Gedung C — FMIPA',
                'koordinat_x' => -7.31150000,
                'koordinat_y' => 112.72800000,
            ],
            [
                'id_kampus' => 1,
                'nama_lokasi' => 'Gedung G — FEB',
                'koordinat_x' => -7.31200000,
                'koordinat_y' => 112.72900000,
            ],
            [
                'id_kampus' => 1,
                'nama_lokasi' => 'Kantin T2 & Gazebo Rektorat',
                'koordinat_x' => -7.31350000,
                'koordinat_y' => 112.72700000,
            ],

            // Kampus 2: Lidah Wetan
            [
                'id_kampus' => 2,
                'nama_lokasi' => 'Kantin T2 & Selasar Lapangan',
                'koordinat_x' => -7.30150000,
                'koordinat_y' => 112.67400000,
            ],
            [
                'id_kampus' => 2,
                'nama_lokasi' => 'Gedung T1 — FBS',
                'koordinat_x' => -7.30250000,
                'koordinat_y' => 112.67550000,
            ],
            [
                'id_kampus' => 2,
                'nama_lokasi' => 'Gedung Rektorat Liwet & Danau',
                'koordinat_x' => -7.30100000,
                'koordinat_y' => 112.67350000,
            ],
        ];

        foreach ($lokasiData as $lokasi) {
            Lokasi::firstOrCreate(
                ['nama_lokasi' => $lokasi['nama_lokasi'], 'id_kampus' => $lokasi['id_kampus']],
                $lokasi
            );
        }
    }
}

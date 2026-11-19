<?php

namespace Database\Seeders;

use App\Models\FotoKucing;
use App\Models\Kampus;
use App\Models\Kucing;
use App\Models\Lokasi;
use Illuminate\Database\Seeder;

/**
 * Roster kucing aktual Jaga Satwa UNESA (Kampus Ketintang & Lidah Wetan).
 * Sumber: spreadsheet presensi + documentations/sources-images/foto-kucing,
 * diringkas ke database/seeders/data/kucing_aktual.json. Foto ada di public/images/kucing.
 *
 * Riwayat kesehatan dan feeding log tidak di-seed di sini: spreadsheet hanya memuat
 * status steril, sedangkan presensi feeding diimpor pada modul dev-backend-06.
 */
class KucingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LokasiSeeder::data()['kampus'] as $kampusData) {
            $idKampus = Kampus::where('nama_kampus', $kampusData['nama_kampus'])->value('id_kampus');

            foreach ($kampusData['lokasi'] as $lok) {
                $idLokasi = Lokasi::where('id_kampus', $idKampus)->where('nama_lokasi', $lok['nama_lokasi'])->value('id_lokasi');

                foreach ($lok['kucing'] as $c) {
                    $kucing = Kucing::firstOrCreate(
                        ['kode_kucing' => $c['kode_kucing']],
                        [
                            'id_lokasi'     => $idLokasi,
                            'nama_kucing'   => $c['nama_kucing'],
                            'jenis_kelamin' => $c['jenis_kelamin'],
                            'kelompok_usia' => $c['kelompok_usia'],
                            'status_steril' => $c['status_steril'],
                            'status_kucing' => $c['status_kucing'],
                            'deskripsi'     => $c['deskripsi'],
                        ]
                    );

                    if ($c['foto']) {
                        FotoKucing::firstOrCreate(
                            ['id_kucing' => $kucing->id_kucing, 'url_foto' => $c['foto']],
                            ['is_utama' => true]
                        );
                    }
                }
            }
        }
    }
}

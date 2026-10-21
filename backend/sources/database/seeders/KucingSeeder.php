<?php

namespace Database\Seeders;

use App\Models\Kucing;
use App\Models\FotoKucing;
use App\Models\RiwayatKesehatan;
use App\Models\PemberianMakan;
use App\Models\Lokasi;
use Illuminate\Database\Seeder;

class KucingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lokasiVokasi = Lokasi::where('nama_lokasi', 'like', '%Vokasi%')->first();
        $lokasiKantinLiwet = Lokasi::where('nama_lokasi', 'like', '%Kantin T2 & Selasar Lapangan%')->first();
        $lokasiRektoratKtt = Lokasi::where('nama_lokasi', 'like', '%Gazebo Rektorat%')->first();
        $lokasiFbs = Lokasi::where('nama_lokasi', 'like', '%FBS%')->first();
        $lokasiFmipa = Lokasi::where('nama_lokasi', 'like', '%FMIPA%')->first();
        $lokasiRektoratLiwet = Lokasi::where('nama_lokasi', 'like', '%Rektorat Liwet%')->first();

        $kucingList = [
            [
                'kode_kucing' => 'KTT-01',
                'nama_kucing' => 'Mochi',
                'id_lokasi' => $lokasiVokasi?->id_lokasi ?? 1,
                'jenis_kelamin' => 'Betina',
                'kelompok_usia' => 'Dewasa',
                'status_steril' => 'Sudah',
                'status_kucing' => 'Tersedia',
                'deskripsi' => 'Kucing belang tiga (calico) paling ramah di selasar Fakultas Vokasi. Rutin feeding harian dan sudah tuntas sterilisasi di Klinik Nusantara Vet.',
                'fotos' => [
                    ['url' => 'images/cat-images/cat1.jpg', 'is_utama' => true],
                    ['url' => 'images/cat-images/cat1-p2.jpg', 'is_utama' => false],
                ],
                'medis' => [
                    ['jenis' => 'Sterilisasi', 'tanggal' => '2026-01-15', 'catatan' => 'Tuntas sterilisasi betina di Klinik Nusantara Vet. Pemulihan sempurna.'],
                    ['jenis' => 'Vaksinasi Tricat', 'tanggal' => '2026-02-02', 'catatan' => 'Vaksinasi tahunan lengkap.'],
                    ['jenis' => 'Pemberian Obat Cacing', 'tanggal' => '2026-09-10', 'catatan' => 'Drontal Cat berkala 3 bulan.'],
                ]
            ],
            [
                'kode_kucing' => 'LWT-01',
                'nama_kucing' => 'Milo',
                'id_lokasi' => $lokasiKantinLiwet?->id_lokasi ?? 5,
                'jenis_kelamin' => 'Jantan',
                'kelompok_usia' => 'Dewasa',
                'status_steril' => 'Sudah',
                'status_kucing' => 'Tersedia',
                'deskripsi' => 'Kucing abu-abu corak tabby dengan kalung biru reflektif. Suka santai di area kantin dan sangat bersahabat dengan mahasiswa.',
                'fotos' => [
                    ['url' => 'images/cat-images/cat2.jpg', 'is_utama' => true],
                ],
                'medis' => [
                    ['jenis' => 'Sterilisasi', 'tanggal' => '2026-02-10', 'catatan' => 'Kastrasi jantan di Klinik Nusantara Vet.'],
                    ['jenis' => 'Vaksinasi Tricat', 'tanggal' => '2026-03-01', 'catatan' => 'Vaksinasi dasar tuntas.'],
                ]
            ],
            [
                'kode_kucing' => 'KTT-02',
                'nama_kucing' => 'Oyen Barbar',
                'id_lokasi' => $lokasiRektoratKtt?->id_lokasi ?? 4,
                'jenis_kelamin' => 'Jantan',
                'kelompok_usia' => 'Dewasa',
                'status_steril' => 'Sudah',
                'status_kucing' => 'Tersedia',
                'deskripsi' => 'Kucing oranye enerjik yang hobi menyapa pengunjung di selasar gedung rektorat. Sehat dan sangat aktif.',
                'fotos' => [
                    ['url' => 'images/cat-images/cat3.jpg', 'is_utama' => true],
                ],
                'medis' => [
                    ['jenis' => 'Sterilisasi', 'tanggal' => '2026-03-12', 'catatan' => 'Kastrasi tuntas.'],
                ]
            ],
            [
                'kode_kucing' => 'LWT-02',
                'nama_kucing' => 'Kismis',
                'id_lokasi' => $lokasiFbs?->id_lokasi ?? 6,
                'jenis_kelamin' => 'Betina',
                'kelompok_usia' => 'Remaja',
                'status_steril' => 'Sudah',
                'status_kucing' => 'Tersedia',
                'deskripsi' => 'Kucing hitam manis bercak putih di dada. Kalung kuning reflektif. Sangat tenang dan cocok untuk hunian keluarga.',
                'fotos' => [
                    ['url' => 'images/cat-images/cat4.jpg', 'is_utama' => true],
                ],
                'medis' => [
                    ['jenis' => 'Sterilisasi', 'tanggal' => '2026-04-05', 'catatan' => 'Sterilisasi betina tuntas.'],
                ]
            ],
            [
                'kode_kucing' => 'KTT-03',
                'nama_kucing' => 'Nori',
                'id_lokasi' => $lokasiFmipa?->id_lokasi ?? 2,
                'jenis_kelamin' => 'Betina',
                'kelompok_usia' => 'Dewasa',
                'status_steril' => 'Sudah',
                'status_kucing' => 'Proses Screening',
                'deskripsi' => 'Kucing kembang telon (tortoiseshell) lembut yang saat ini sedang dalam proses review calon adopter.',
                'fotos' => [
                    ['url' => 'images/cat-images/cat5.jpg', 'is_utama' => true],
                ],
                'medis' => [
                    ['jenis' => 'Sterilisasi', 'tanggal' => '2026-01-20', 'catatan' => 'Sterilisasi betina tuntas.'],
                ]
            ],
            [
                'kode_kucing' => 'LWT-03',
                'nama_kucing' => 'Luna',
                'id_lokasi' => $lokasiRektoratLiwet?->id_lokasi ?? 7,
                'jenis_kelamin' => 'Betina',
                'kelompok_usia' => 'Dewasa',
                'status_steril' => 'Sudah',
                'status_kucing' => 'Terdata',
                'deskripsi' => 'Kucing betina putih corak abu-abu di sekitar perpustakaan Liwet. Kalung ungu reflektif, sehat dan aktif.',
                'fotos' => [
                    ['url' => 'images/cat-images/cat6.jpg', 'is_utama' => true],
                ],
                'medis' => [
                    ['jenis' => 'Sterilisasi', 'tanggal' => '2026-05-18', 'catatan' => 'Sterilisasi betina tuntas.'],
                ]
            ],
        ];

        foreach ($kucingList as $data) {
            $kucing = Kucing::firstOrCreate(
                ['kode_kucing' => $data['kode_kucing']],
                [
                    'nama_kucing' => $data['nama_kucing'],
                    'id_lokasi' => $data['id_lokasi'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'kelompok_usia' => $data['kelompok_usia'],
                    'status_steril' => $data['status_steril'],
                    'status_kucing' => $data['status_kucing'],
                    'deskripsi' => $data['deskripsi'],
                ]
            );

            // Foto Galeri
            foreach ($data['fotos'] as $foto) {
                FotoKucing::firstOrCreate(
                    ['id_kucing' => $kucing->id_kucing, 'url_foto' => $foto['url']],
                    ['is_utama' => $foto['is_utama']]
                );
            }

            // Rekam Medis
            foreach ($data['medis'] as $med) {
                RiwayatKesehatan::firstOrCreate(
                    [
                        'id_kucing' => $kucing->id_kucing,
                        'jenis_tindakan' => $med['jenis'],
                        'tanggal' => $med['tanggal']
                    ],
                    ['catatan' => $med['catatan']]
                );
            }

            // Sample Log Pemberian Makan
            PemberianMakan::firstOrCreate(
                [
                    'id_kucing' => $kucing->id_kucing,
                    'tanggal' => now()->toDateString(),
                ],
                [
                    'id_lokasi' => $kucing->id_lokasi,
                    'status_pemberian' => 'Tuntas',
                    'catatan' => 'Pakan kering 65gr + air bersih (Shift Pagi & Sore).'
                ]
            );
        }
    }
}

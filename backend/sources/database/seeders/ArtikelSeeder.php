<?php

namespace Database\Seeders;

use App\Models\Artikel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArtikelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first() ?? User::first();

        $artikels = [
            [
                'id_user' => $admin?->id_user ?? 1,
                'judul' => 'Kenapa Kucing UNESA Pakai Kalung? Jangan Sembarangan Dilepas!',
                'slug' => Str::slug('Kenapa Kucing UNESA Pakai Kalung Jangan Sembarangan Dilepas'),
                'kategori' => 'Edukasi & Kampanye',
                'isi' => 'Ketahui alasan vital penggunaan kalung reflektif kucing kampus UNESA: tanda steril, identitas resmi, dan proteksi keselamatan malam hari dari kendaraan kampus.',
                'thumbnail_url' => 'images/images-blogs/blog1.jpg',
                'status_publikasi' => 'PUBLISHED',
                'is_featured' => true,
                'is_pinned' => true,
                'tanggal_dibuat' => '2026-09-30',
                'tanggal_diterbitkan' => '2026-09-30',
            ],
            [
                'id_user' => $admin?->id_user ?? 1,
                'judul' => 'Program TNR Sterilisasi Massal Kampus Lidah Wetan 2026',
                'slug' => Str::slug('Program TNR Sterilisasi Massal Kampus Lidah Wetan 2026'),
                'kategori' => 'Kesehatan Veteriner',
                'isi' => 'Jaga Satwa UNESA bekerja sama dengan Klinik Nusantara Vet menyelenggarakan program Trap-Neuter-Return (TNR) berkala untuk mengontrol populasi satwa kampus secara humanis.',
                'thumbnail_url' => 'images/images-blogs/blog2.jpg',
                'status_publikasi' => 'PUBLISHED',
                'is_featured' => false,
                'is_pinned' => false,
                'tanggal_dibuat' => '2026-10-04',
                'tanggal_diterbitkan' => '2026-10-04',
            ],
            [
                'id_user' => $admin?->id_user ?? 1,
                'judul' => 'Panduan Mengadopsi Kucing Kampus: Hunian Indoor Safe',
                'slug' => Str::slug('Panduan Mengadopsi Kucing Kampus Hunian Indoor Safe'),
                'kategori' => 'Panduan Adopsi',
                'isi' => 'Sebelum mengajukan adopsi, pastikan Anda memahami komitmen pemeliharaan indoor penuh kasih sayang dan bersedia melaporkan kondisi pemantauan pasca-adopsi.',
                'thumbnail_url' => 'images/images-blogs/blog3.jpg',
                'status_publikasi' => 'PUBLISHED',
                'is_featured' => false,
                'is_pinned' => false,
                'tanggal_dibuat' => '2026-10-06',
                'tanggal_diterbitkan' => '2026-10-06',
            ],
        ];

        foreach ($artikels as $art) {
            Artikel::firstOrCreate(
                ['slug' => $art['slug']],
                $art
            );
        }
    }
}

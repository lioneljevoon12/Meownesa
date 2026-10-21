<?php

namespace Database\Seeders;

use App\Models\Kampus;
use Illuminate\Database\Seeder;

class KampusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Kampus::firstOrCreate(
            ['nama_kampus' => 'Kampus Ketintang'],
            ['id_kampus' => 1]
        );

        Kampus::firstOrCreate(
            ['nama_kampus' => 'Kampus Lidah Wetan'],
            ['id_kampus' => 2]
        );
    }
}

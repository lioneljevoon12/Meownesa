<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Modul: dev-backend-01
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            KampusSeeder::class,
            LokasiSeeder::class,
            KucingSeeder::class,
            ArtikelSeeder::class,
        ]);
    }
}

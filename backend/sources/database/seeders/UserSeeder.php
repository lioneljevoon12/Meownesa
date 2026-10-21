<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Super Admin (Teknis & Manajemen Akun)
        User::firstOrCreate(
            ['email' => 'superadmin@unesa.ac.id'],
            [
                'nama' => 'Super Admin MeowNesa',
                'password_hash' => Hash::make('admin123'),
                'role' => 'superadmin',
                'status_akun' => 'aktif',
            ]
        );

        // 2. Admin Jaga Satwa (Pengelola Operasional Harian)
        User::firstOrCreate(
            ['email' => 'admin@jagasatwa.unesa.ac.id'],
            [
                'nama' => 'Admin Jaga Satwa UNESA',
                'password_hash' => Hash::make('admin123'),
                'role' => 'admin',
                'status_akun' => 'aktif',
            ]
        );

        // 3. Relawan Kampus Ketintang (Admin Operasional)
        User::firstOrCreate(
            ['email' => 'relawan.ketintang@unesa.ac.id'],
            [
                'nama' => 'Relawan Feeder Ketintang',
                'password_hash' => Hash::make('admin123'),
                'role' => 'admin',
                'status_akun' => 'aktif',
            ]
        );
    }
}

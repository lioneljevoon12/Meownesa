<?php

namespace Tests\Concerns;

use App\Models\PengajuanAdopsi;
use App\Models\ScreeningAdopsi;
use App\Models\User;

/** Pembuat pengajuan adopsi untuk tes modul 08 (langsung lewat model agar status mudah diatur). */
trait MembuatPengajuan
{
    protected function buatPengajuan(?object $kucing = null, string $status = 'MENUNGGU', array $o = []): PengajuanAdopsi
    {
        static $n = 0;
        $n++;
        $kucing ??= $this->kucing;

        return PengajuanAdopsi::create($o + [
            'id_kucing'         => $kucing->id_kucing,
            'nomor_pengajuan'   => sprintf('ADP-2026-%03d', $n),
            'kode_tracking'     => sprintf('MN-TEST-%04d-ZZZZ', $n),
            'nama_pemohon'      => 'Pemohon Nomor '.$n,
            'nomor_whatsapp'    => sprintf('62811000%04d', $n),
            'email'             => "pemohon{$n}@contoh.id",
            'alamat'            => "Jl. Contoh No. {$n}, Surabaya",
            'status_pengajuan'  => $status,
            'tanggal_pengajuan' => '2026-11-0'.(($n % 9) + 1),
        ]);
    }

    protected function buatScreening(PengajuanAdopsi $p, string $hasil = 'Lolos', ?User $admin = null): ScreeningAdopsi
    {
        $admin ??= User::firstOrCreate(['email' => 'petugas@uji.test'], [
            'nama' => 'Petugas Uji', 'password_hash' => 'Rahasia-123', 'role' => 'admin', 'status_akun' => 'aktif',
        ]);

        return ScreeningAdopsi::create([
            'id_pengajuan' => $p->id_pengajuan, 'id_user' => $admin->id_user,
            'kelayakan_hunian' => 'Indoor aman', 'kestabilan_ekonomi' => 'Stabil', 'pemahaman_perawatan' => 'Paham', 'sikap_proklinik' => 'Bersedia',
            'hasil_screening' => $hasil, 'tanggal_screening' => '2026-11-10',
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tata kelola akun oleh Super Admin (dev-backend-14).
 *
 * Super Admin TIDAK pernah mengetahui password siapa pun: akun admin baru dibuat dengan password acak yang
 * tak diketahui siapa pun dan status `menunggu_aktivasi`, lalu pemiliknya mengatur sendiri passwordnya lewat
 * tautan sekali pakai yang dikirim ke emailnya (alur yang sama dengan aktivasi akun adopter, modul 09).
 * Tautan itu hanya ada di isi pesan notifikasi (yang tidak bisa dibaca admin biasa) dan tidak dikembalikan
 * oleh API.
 */
class AkunPengurusService
{
    /** Role yang boleh dikelola lewat API. Akun Super Admin dikelola di luar API (seeder/konsol). */
    public const ROLE_DIKELOLA = [User::ROLE_ADMIN, User::ROLE_ADOPTER];

    public function __construct(private readonly AktivasiAkunService $aktivasi)
    {
    }

    /** Buat akun Admin Jaga Satwa baru (menunggu aktivasi) beserta undangan emailnya. @return array{0: User, 1: Notifikasi} */
    public function buatAdmin(string $nama, string $email): array
    {
        return DB::transaction(function () use ($nama, $email) {
            $user = User::create([
                'nama'          => $nama,
                'email'         => $email,
                'password_hash' => Str::random(48),   // di-hash oleh cast; tidak diketahui siapa pun
                'role'          => User::ROLE_ADMIN,
                'status_akun'   => User::STATUS_MENUNGGU_AKTIVASI,
            ]);

            return [$user, $this->undang($user, Notifikasi::JENIS_UNDANGAN_PENGURUS)];
        });
    }

    /**
     * Reset kredensial: sesi login dicabut, akun kembali `menunggu_aktivasi`, dan tautan baru dikirim.
     * Untuk akun yang memang masih menunggu aktivasi, ini sama dengan mengirim ulang undangan.
     */
    public function resetPassword(User $user): Notifikasi
    {
        return DB::transaction(function () use ($user) {
            $jenis = $user->status_akun === User::STATUS_MENUNGGU_AKTIVASI
                ? Notifikasi::JENIS_UNDANGAN_PENGURUS
                : Notifikasi::JENIS_RESET_PASSWORD;

            $user->tokens()->delete();
            $user->forceFill(['status_akun' => User::STATUS_MENUNGGU_AKTIVASI])->save();

            return $this->undang($user, $jenis);
        });
    }

    /** Ubah status aktif/nonaktif. Menonaktifkan langsung mengeluarkan semua sesi login. */
    public function ubahStatus(User $user, string $status): void
    {
        $user->forceFill(['status_akun' => $status])->save();

        if ($status === 'nonaktif') {
            $user->tokens()->delete();
        }
    }

    /** Antrekan email berisi tautan aktivasi baru (token lama otomatis tidak berlaku). */
    public function undang(User $user, string $jenis): Notifikasi
    {
        $tautan = $this->aktivasi->buatTautan($user);
        $nama = explode(' ', trim($user->nama))[0];

        $pesan = $jenis === Notifikasi::JENIS_RESET_PASSWORD
            ? "Halo {$nama}, password akun MeowNesa Anda direset oleh Super Admin dan semua sesi login lama sudah dikeluarkan. "
              ."Atur password baru di: {$tautan} (berlaku 7 hari). Jika Anda tidak merasa memintanya, hubungi Super Admin."
            : "Halo {$nama}, akun pengurus MeowNesa Anda sudah dibuat. Atur password Anda di: {$tautan} (berlaku 7 hari), "
              .'lalu masuk dengan email ini.';

        return Notifikasi::create([
            'id_pengajuan'      => null,
            'id_user'           => $user->id_user,
            'jenis_notifikasi'  => $jenis,
            'kanal'             => Notifikasi::KANAL_EMAIL,
            'isi_pesan'         => $pesan,
            'status_pengiriman' => Notifikasi::STATUS_PENDING,
        ]);
    }
}

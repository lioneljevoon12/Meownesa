<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Aktivasi akun adopter lewat tautan sekali pakai (proposal 2.3.1: "mekanisme aktivasi akun").
 *
 * Akun dibuat saat serah terima dengan password acak yang tidak diketahui siapa pun dan status
 * `menunggu_aktivasi` (tidak bisa login). Adopter mengatur passwordnya sendiri lewat tautan berisi token
 * acak: tidak ada password yang dikirim lewat chat atau email. Token memakai broker bawaan Laravel
 * (`aktivasi`): disimpan ter-hash, sekali pakai, dan kedaluwarsa 7 hari.
 */
class AktivasiAkunService
{
    public const BROKER = 'aktivasi';

    /** Buat token baru (token lama otomatis tergantikan) dan kembalikan tautan lengkapnya. */
    public function buatTautan(User $user): string
    {
        $token = Password::broker(self::BROKER)->createToken($user);

        return rtrim((string) config('app.frontend_url'), '/').'/aktivasi-akun?'.http_build_query([
            'email' => $user->email,
            'token' => $token,
        ]);
    }

    /**
     * Atur password dan aktifkan akun. Mengembalikan false untuk SEMUA kegagalan (email tak dikenal,
     * bukan akun adopter yang menunggu aktivasi, token salah/kedaluwarsa/sudah dipakai) agar
     * penelepon tidak dapat membedakannya.
     */
    public function aktifkan(string $email, string $token, string $password): ?User
    {
        $user = User::where('email', mb_strtolower(trim($email)))->first();

        // Akun yang dinonaktifkan Super Admin tidak boleh "hidup lagi" lewat tautan lama.
        if (! $user || $user->role !== User::ROLE_ADOPTER || $user->status_akun !== User::STATUS_MENUNGGU_AKTIVASI) {
            return null;
        }

        $aktif = null;
        $status = Password::broker(self::BROKER)->reset(
            ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password],
            function (User $u, string $passwordBaru) use (&$aktif) {
                $u->forceFill(['password_hash' => $passwordBaru, 'status_akun' => 'aktif'])->save();   // cast `hashed` meng-hash
                $aktif = $u;
            },
        );

        return $status === Password::PASSWORD_RESET ? $aktif : null;
    }
}

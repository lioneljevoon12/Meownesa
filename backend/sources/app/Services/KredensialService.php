<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Kredensial yang diurus pemilik akunnya sendiri (dev-backend-16): ubah password, lupa/reset password lewat email,
 * dan pencabutan sesi login.
 *
 * Lupa password TIDAK pernah membocorkan apakah sebuah email terdaftar: respons API selalu sama dan pekerjaannya
 * dilakukan setelah respons terkirim. Tautan reset sekali pakai, berlaku 60 menit (broker `users`), hanya ada di
 * isi email (notifikasi akun pengguna tidak terlihat admin biasa) dan disamarkan di basis data begitu terkirim.
 * Akun yang belum diaktifkan atau ditangguhkan tidak bisa mereset lewat jalur ini.
 */
class KredensialService
{
    public const BROKER = 'users';

    /** Selang minimal antar-permintaan tautan reset untuk akun yang sama (detik). */
    public const JEDA_DETIK = 60;

    public function __construct(private readonly PengirimanNotifikasiService $pengiriman)
    {
    }

    /** Ganti password dan keluarkan SEMUA sesi lain (sesi yang sedang dipakai tetap hidup). */
    public function ubahPassword(User $user, string $passwordBaru, ?int $idTokenSaatIni): void
    {
        $user->forceFill(['password_hash' => $passwordBaru])->save();   // cast `hashed` meng-hash

        $user->tokens()->when($idTokenSaatIni, fn ($q) => $q->where('id', '!=', $idTokenSaatIni))->delete();

        AuditLogger::record($user, 'UBAH_PASSWORD', 'users', $user->id_user);
    }

    /**
     * Antrekan email tautan reset bila emailnya milik akun aktif. Tidak mengembalikan apa pun agar pemanggil
     * tidak bisa membedakan email terdaftar atau tidak.
     */
    public function mintaReset(string $email): void
    {
        $user = User::where('email', mb_strtolower(trim($email)))->first();

        if (! $user || ! $user->isActive()) {
            return;
        }

        $broker = Password::broker(self::BROKER);
        if ($broker->getRepository()->recentlyCreatedToken($user)) {
            return;   // sudah dikirim barusan: cegah banjir email ke satu alamat
        }

        $token = $broker->createToken($user);
        $tautan = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query(['email' => $user->email, 'token' => $token]);
        $nama = explode(' ', trim($user->nama))[0];

        $notifikasi = Notifikasi::create([
            'id_pengajuan'      => null,
            'id_user'           => $user->id_user,
            'jenis_notifikasi'  => Notifikasi::JENIS_LUPA_PASSWORD,
            'kanal'             => Notifikasi::KANAL_EMAIL,
            'isi_pesan'         => "Halo {$nama}, kami menerima permintaan reset password akun MeowNesa Anda. Atur password baru di: {$tautan} "
                .'(berlaku 60 menit, sekali pakai). Jika bukan Anda yang meminta, abaikan email ini; password Anda tidak berubah.',
            'status_pengiriman' => Notifikasi::STATUS_PENDING,
        ]);

        AuditLogger::record($user, 'LUPA_PASSWORD_DIMINTA', 'users', $user->id_user);

        $this->pengiriman->kirimEmail($notifikasi->load('user'));
    }

    /**
     * Atur password baru lewat tautan. Mengembalikan null untuk SEMUA kegagalan (email tak dikenal, akun tidak aktif,
     * token salah/kedaluwarsa/sudah dipakai) agar tidak bisa dipakai menebak akun. Semua sesi login lama dicabut.
     */
    public function reset(string $email, string $token, string $password): ?User
    {
        $user = User::where('email', mb_strtolower(trim($email)))->first();

        if (! $user || ! $user->isActive()) {
            return null;
        }

        $hasil = null;
        $status = Password::broker(self::BROKER)->reset(
            ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password],
            function (User $u, string $passwordBaru) use (&$hasil) {
                $u->forceFill(['password_hash' => $passwordBaru])->save();
                $u->tokens()->delete();
                $hasil = $u;
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $hasil) {
            return null;
        }

        AuditLogger::record($hasil, 'RESET_PASSWORD_MANDIRI', 'users', $hasil->id_user);

        return $hasil;
    }
}

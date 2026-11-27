<?php

namespace App\Http\Controllers;

use App\Http\Requests\LupaPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\KredensialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Lupa/reset password mandiri (publik, dev-backend-16). Dibatasi throttle: 3/menit per email+IP dan 10/jam per IP
 * untuk permintaan tautan, 5/menit per email+IP untuk pengaturan password.
 */
class LupaPasswordController extends Controller
{
    public function __construct(private readonly KredensialService $kredensial)
    {
    }

    /**
     * Minta tautan reset. Respons SELALU sama (juga untuk email yang tidak terdaftar, belum aktif, atau ditangguhkan),
     * dan seluruh pekerjaannya dilakukan setelah respons terkirim agar waktu responsnya pun tidak membocorkan apa-apa.
     */
    public function minta(LupaPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');

        defer(fn () => $this->kredensial->mintaReset($email));

        return response()->json([
            'success' => true,
            'message' => 'Jika email itu terdaftar dan akunnya aktif, tautan untuk mengatur ulang password sudah dikirim. Periksa kotak masuk dan folder spam.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $user = $this->kredensial->reset($request->validated('email'), $request->validated('token'), $request->validated('password'));

        if (! $user) {
            // Satu pesan untuk semua penyebab agar tidak bisa dipakai menebak akun.
            throw ValidationException::withMessages([
                'token' => 'Tautan reset password tidak valid atau sudah kedaluwarsa. Minta tautan baru lewat "Lupa password".',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diatur ulang. Semua sesi login lama sudah dikeluarkan; silakan masuk dengan password baru.',
        ]);
    }
}

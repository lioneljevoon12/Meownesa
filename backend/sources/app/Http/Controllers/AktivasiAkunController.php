<?php

namespace App\Http\Controllers;

use App\Http\Requests\AktivasiAkunRequest;
use App\Services\AktivasiAkunService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/** Aktivasi akun adopter lewat tautan sekali pakai (publik). Dibatasi throttle:login (5/menit per email+IP). */
class AktivasiAkunController extends Controller
{
    public function __construct(private readonly AktivasiAkunService $aktivasi)
    {
    }

    public function aktifkan(AktivasiAkunRequest $request): JsonResponse
    {
        $user = $this->aktivasi->aktifkan($request->input('email'), $request->input('token'), $request->input('password'));

        if (! $user) {
            // Satu pesan untuk semua penyebab (token salah, kedaluwarsa, sudah dipakai, email tak dikenal, akun
            // dinonaktifkan) agar tidak bisa dipakai menebak akun.
            throw ValidationException::withMessages([
                'token' => 'Tautan aktivasi tidak valid atau sudah kedaluwarsa. Minta pengurus Jaga Satwa mengirim ulang tautannya.',
            ]);
        }

        AuditLogger::record($user, 'AKTIVASI_AKUN', 'users', $user->id_user);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diaktifkan. Silakan masuk dengan email dan password yang baru Anda atur.',
        ]);
    }
}

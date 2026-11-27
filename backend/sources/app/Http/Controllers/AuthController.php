<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Autentikasi API (MEOW-F-41, MEOW-NF-01, MEOW-NF-03).
 * Token Bearer via Laravel Sanctum; masa berlaku diatur SANCTUM_EXPIRATION (menit).
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'string', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        // Samakan waktu respons untuk email yang tidak terdaftar (cegah user enumeration via timing).
        if (! $user) {
            Hash::make($data['password']);
        }

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            AuditLogger::record($user, 'LOGIN_GAGAL', 'users', $user?->id_user);

            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        if (! $user->isActive()) {
            AuditLogger::record($user, 'LOGIN_DITOLAK', 'users', $user->id_user);

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.',
            ], 403);
        }

        // Naikkan hash lama ke cost saat ini bila perlu.
        if (Hash::needsRehash($user->password_hash)) {
            $user->forceFill(['password_hash' => $data['password']])->save();
        }

        [$plain, $expiresAt] = $this->terbitkanToken($user);

        AuditLogger::record($user, 'LOGIN', 'users', $user->id_user);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => $this->tokenPayload($plain, $expiresAt, $user),
        ]);
    }

    /**
     * Tukar token yang masih berlaku dengan token baru (masa berlaku dimulai ulang) dan cabut yang lama. Dipakai PWA
     * agar petugas lapangan yang aktif tidak perlu login ulang tiap beberapa jam. Token yang sudah kedaluwarsa tidak
     * bisa diperpanjang (harus login lagi); antrean tulis offline di HP tetap aman dan terkirim setelah login ulang.
     */
    public function perpanjang(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isActive()) {
            return response()->json(['success' => false, 'message' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.'], 403);
        }

        $lama = $user->currentAccessToken();
        [$plain, $expiresAt] = $this->terbitkanToken($user);

        if ($lama instanceof SanctumToken) {
            $lama->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Token diperpanjang.',
            'data'    => $this->tokenPayload($plain, $expiresAt, $user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if ($token instanceof SanctumToken) {
            $token->delete();
        }

        AuditLogger::record($user, 'LOGOUT', 'users', $user->id_user);

        return response()->json(['success' => true, 'message' => 'Logout berhasil.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.',
            ], 403);
        }

        $token = $user->currentAccessToken();

        return response()->json([
            'success' => true,
            'data'    => $this->userPayload($user) + [
                // Kapan token ini berakhir (null = tidak kedaluwarsa), supaya PWA tahu kapan perlu memperpanjang.
                'token_kedaluwarsa_pada' => $token instanceof SanctumToken ? $token->expires_at?->toIso8601String() : null,
            ],
        ]);
    }

    /** @return array{0: string, 1: \Carbon\Carbon|null} token mentah dan waktu kedaluwarsanya */
    private function terbitkanToken(User $user): array
    {
        $expiresAt = config('sanctum.expiration') ? now()->addMinutes((int) config('sanctum.expiration')) : null;

        return [$user->createToken('api', ['*'], $expiresAt)->plainTextToken, $expiresAt];
    }

    /** @return array<string, mixed> */
    private function tokenPayload(string $plain, ?\Carbon\Carbon $expiresAt, User $user): array
    {
        return [
            'token'      => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt?->toIso8601String(),
            'user'       => $this->userPayload($user),
        ];
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'id_user'     => $user->id_user,
            'nama'        => $user->nama,
            'email'       => $user->email,
            'role'        => $user->role,
            'status_akun' => $user->status_akun,
            'akses'       => $user->akses(),
        ];
    }
}

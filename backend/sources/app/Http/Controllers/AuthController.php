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

        $expiresAt = config('sanctum.expiration') ? now()->addMinutes((int) config('sanctum.expiration')) : null;
        $token = $user->createToken('api', ['*'], $expiresAt);

        AuditLogger::record($user, 'LOGIN', 'users', $user->id_user);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt?->toIso8601String(),
                'user'       => $this->userPayload($user),
            ],
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

        return response()->json([
            'success' => true,
            'data'    => $this->userPayload($user),
        ]);
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

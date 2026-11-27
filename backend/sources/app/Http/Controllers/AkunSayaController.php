<?php

namespace App\Http\Controllers;

use App\Http\Requests\UbahPasswordRequest;
use App\Http\Requests\UbahProfilRequest;
use App\Models\PemberianMakan;
use App\Services\AuditLogger;
use App\Services\KredensialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Pengelolaan akun oleh pemiliknya sendiri (dev-backend-16): ubah password, nama, dan sesi login.
 * Route: auth:sanctum (semua role). Hanya akun milik pemanggil yang dapat disentuh, tidak pernah akun lain.
 */
class AkunSayaController extends Controller
{
    public function __construct(private readonly KredensialService $kredensial)
    {
    }

    /** Ganti password: butuh password lama; semua sesi lain dikeluarkan, sesi ini tetap hidup. */
    public function ubahPassword(UbahPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isActive()) {
            return $this->ditangguhkan();
        }

        if (! Hash::check($request->validated('password_lama'), $user->password_hash)) {
            AuditLogger::record($user, 'UBAH_PASSWORD_GAGAL', 'users', $user->id_user);

            return response()->json([
                'success' => false,
                'message' => 'Password lama salah.',
                'errors'  => ['password_lama' => ['Password lama salah.']],
            ], 422);
        }

        $token = $user->currentAccessToken();
        $this->kredensial->ubahPassword($user, $request->validated('password'), $token instanceof PersonalAccessToken ? $token->id : null);

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah. Sesi login di perangkat lain sudah dikeluarkan.']);
    }

    /** Ubah nama sendiri. */
    public function ubahProfil(UbahProfilRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isActive()) {
            return $this->ditangguhkan();
        }

        $user->update($request->validated());

        // id_user harus kunci pertama: dipakai middleware audit sebagai id_data.
        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => ['id_user' => $user->id_user, 'nama' => $user->nama, 'email' => $user->email, 'role' => $user->role],
        ]);
    }

    /** Sesi login aktif (perangkat) milik sendiri. */
    public function daftarSesi(Request $request): JsonResponse
    {
        $user = $request->user();
        $saatIni = $user->currentAccessToken();

        $sesi = $user->tokens()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('last_used_at')->orderByDesc('id')->get()
            ->map(fn (PersonalAccessToken $t) => [
                'id_sesi'        => $t->id,
                'perangkat'      => $t->name,
                'dibuat_pada'    => $t->created_at?->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String(),
                'terakhir_dipakai' => $t->last_used_at?->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String(),
                'kedaluwarsa_pada' => $t->expires_at?->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String(),
                'saat_ini'       => $saatIni instanceof PersonalAccessToken && $saatIni->id === $t->id,
            ])->values();

        return response()->json(['success' => true, 'data' => $sesi]);
    }

    /** Keluarkan satu sesi. Hanya sesi milik sendiri; milik orang lain dijawab 404 (tidak membocorkan keberadaannya). */
    public function cabutSesi(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $sesi = $user->tokens()->whereKey($id)->firstOrFail();
        $sesi->delete();

        AuditLogger::record($user, 'CABUT_SESI', 'users', $user->id_user);

        return response()->json(['success' => true, 'message' => 'Sesi berhasil dikeluarkan.', 'data' => ['id_sesi' => $id]]);
    }

    /** Keluarkan semua sesi lain (sesi ini tetap hidup), mis. HP hilang. */
    public function keluarSemuaLain(Request $request): JsonResponse
    {
        $user = $request->user();
        $saatIni = $user->currentAccessToken();

        $jumlah = $user->tokens()
            ->when($saatIni instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $saatIni->id))
            ->delete();

        AuditLogger::record($user, 'CABUT_SEMUA_SESI', 'users', $user->id_user);

        return response()->json(['success' => true, 'message' => "{$jumlah} sesi lain dikeluarkan.", 'data' => ['jumlah' => $jumlah]]);
    }

    private function ditangguhkan(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.'], 403);
    }
}

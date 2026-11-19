<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pembatasan akses berdasarkan role (MEOW-NF-02).
 * Pemakaian di route: ->middleware(['auth:sanctum', 'role:admin,superadmin'])
 *
 * Pencocokan bersifat eksak: Super Admin tidak otomatis lolos route `role:admin`
 * (Super Admin tidak mengambil keputusan operasional/adopsi).
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak terautentikasi. Silakan login terlebih dahulu.',
            ], 401);
        }

        // Akun yang dinonaktifkan Super Admin langsung kehilangan akses walau token masih berlaku.
        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.',
            ], 403);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk sumber daya ini.',
            ], 403);
        }

        return $next($request);
    }
}

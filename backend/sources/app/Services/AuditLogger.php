<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Throwable;

/**
 * Pencatat aktivitas penting ke tabel audit_log (MEOW-F-44, MEOW-NF-07).
 * Kegagalan mencatat tidak boleh menggagalkan aksi utama pengguna.
 */
class AuditLogger
{
    public static function record(?User $user, string $aktivitas, ?string $namaTabel = null, ?int $idData = null): void
    {
        try {
            AuditLog::create([
                'id_user'         => $user?->id_user,
                'aktivitas'       => mb_substr($aktivitas, 0, 100),
                'nama_tabel'      => $namaTabel ? mb_substr($namaTabel, 0, 50) : null,
                'id_data'         => $idData,
                'waktu_aktivitas' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}

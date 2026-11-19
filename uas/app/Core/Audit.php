<?php
declare(strict_types=1);

namespace App\Core;

/** Pencatatan aktivitas penting ke tabel audit_log (MEOW-F-44, MEOW-NF-07). */
final class Audit
{
    public static function log(string $aktivitas, ?string $tabel = null, ?int $idData = null): void
    {
        try {
            Db::insert('audit_log', [
                'id_user'         => Auth::user()['id'] ?? null,
                'aktivitas'       => $aktivitas,
                'nama_tabel'      => $tabel,
                'id_data'         => $idData,
                'waktu_aktivitas' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Kegagalan audit tidak boleh menggagalkan aksi utama.
        }
    }
}

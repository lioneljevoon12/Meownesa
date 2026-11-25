<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat otomatis setiap mutasi data yang BERHASIL (POST/PUT/PATCH/DELETE) ke audit_log.
 *
 * Pemakaian (letakkan SETELAH auth:sanctum):
 *   ->middleware(['auth:sanctum', 'role:admin', 'audit:kucing'])
 *   ->middleware('audit:pengajuan_adopsi,UBAH_STATUS')   // nama aktivitas kustom
 *
 * id_data diambil dari parameter route `id` atau, bila tidak ada (mis. POST create),
 * dari kunci primary key `id_*` pertama pada `data` di respons JSON.
 */
class RecordAuditLog
{
    /**
     * Atribut request yang diset controller bila aksi yang sukses ternyata tidak mengubah data
     * (mis. pengiriman ulang idempoten), supaya audit log tidak mencatat perubahan palsu.
     */
    public const LEWATI = 'audit.lewati';

    private const AKTIVITAS = [
        'POST'   => 'CREATE',
        'PUT'    => 'UPDATE',
        'PATCH'  => 'UPDATE',
        'DELETE' => 'DELETE',
    ];

    public function handle(Request $request, Closure $next, ?string $tabel = null, ?string $aktivitas = null): Response
    {
        $response = $next($request);

        if (! isset(self::AKTIVITAS[$request->method()]) || $response->getStatusCode() >= 400 || $request->attributes->get(self::LEWATI)) {
            return $response;
        }

        $user = $request->user();
        if (! $user) {
            return $response;
        }

        AuditLogger::record(
            $user,
            $aktivitas ?? self::AKTIVITAS[$request->method()],
            $tabel ?? $this->tabelDariUri($request),
            $this->idData($request, $response),
        );

        return $response;
    }

    private function tabelDariUri(Request $request): ?string
    {
        // api/admin/kucing/5/foto -> "kucing"
        $segments = $request->segments();
        $i = array_search($segments[1] ?? '', ['admin', 'superadmin'], true) !== false ? 2 : 1;

        return isset($segments[$i]) ? str_replace('-', '_', $segments[$i]) : null;
    }

    private function idData(Request $request, Response $response): ?int
    {
        $id = $request->route('id');
        if (is_numeric($id)) {
            return (int) $id;
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true)['data'] ?? null;
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (str_starts_with((string) $key, 'id_') && is_int($value)) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }
}

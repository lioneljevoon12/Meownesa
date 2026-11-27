<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\PemberianMakan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Penampil rekam jejak audit (MEOW-F-44, MEOW-NF-07). Route: role:superadmin. Hanya BACA: tidak ada endpoint
 * untuk mengubah atau menghapus audit log, siapa pun perannya, sehingga rekam jejak tidak bisa dihapus lewat API.
 */
class AuditLogController extends Controller
{
    /** Terbaru di atas. Filter: nama_tabel, aktivitas, id_user, id_data, dari/sampai (WIB, inklusif). */
    public function index(FilterAuditLogRequest $request): JsonResponse
    {
        $f = $request->validated();

        $halaman = AuditLog::query()
            ->with('user:id_user,nama,email,role')
            ->when($f['nama_tabel'] ?? null, fn (Builder $q, $v) => $q->where('nama_tabel', $v))
            ->when($f['aktivitas'] ?? null, fn (Builder $q, $v) => $q->where('aktivitas', $v))
            ->when($f['id_user'] ?? null, fn (Builder $q, $v) => $q->where('id_user', $v))
            ->when($f['id_data'] ?? null, fn (Builder $q, $v) => $q->where('id_data', $v))
            ->when($f['dari'] ?? null, fn (Builder $q, $v) => $q->where('waktu_aktivitas', '>=', $this->batasWaktu($v, awal: true)))
            ->when($f['sampai'] ?? null, fn (Builder $q, $v) => $q->where('waktu_aktivitas', '<=', $this->batasWaktu($v, awal: false)))
            ->when(($f['sort'] ?? 'terbaru') === 'terlama',
                fn (Builder $q) => $q->orderBy('waktu_aktivitas')->orderBy('id_audit'),
                fn (Builder $q) => $q->orderByDesc('waktu_aktivitas')->orderByDesc('id_audit'))
            ->paginate($f['per_page'] ?? 25, ['*'], 'page', $f['page'] ?? 1);

        return response()->json([
            'success' => true,
            'data'    => AuditLogResource::collection($halaman->getCollection())->resolve(),
            'meta'    => [
                'halaman'       => $halaman->currentPage(),
                'halaman_akhir' => $halaman->lastPage(),
                'per_halaman'   => $halaman->perPage(),
                'total'         => $halaman->total(),
            ],
        ]);
    }

    /** Nilai yang tersedia untuk dropdown filter (nama tabel dan jenis aktivitas yang pernah tercatat). */
    public function opsi(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'nama_tabel' => AuditLog::whereNotNull('nama_tabel')->distinct()->orderBy('nama_tabel')->pluck('nama_tabel')->all(),
                'aktivitas'  => AuditLog::distinct()->orderBy('aktivitas')->pluck('aktivitas')->all(),
            ],
        ]);
    }

    /** Awal/akhir hari WIB, dikonversi ke zona aplikasi (UTC) karena itu zona penyimpanan waktu_aktivitas. */
    private function batasWaktu(string $tanggal, bool $awal): Carbon
    {
        $hari = Carbon::createFromFormat('Y-m-d', $tanggal, PemberianMakan::ZONA_WAKTU);

        return ($awal ? $hari->startOfDay() : $hari->endOfDay())->setTimezone(config('app.timezone'));
    }
}

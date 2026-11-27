<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RecordAuditLog;
use App\Http\Requests\FilterUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UbahStatusUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserAkunResource;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\User;
use App\Services\AkunPengurusService;
use App\Services\PengirimanNotifikasiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Tata kelola akun oleh Super Admin (MEOW-F-42, F-43, proposal hal. 16 dan 43). Route: role:superadmin +
 * audit:users. Super Admin mengurus aspek teknis akun, bukan keputusan operasional (screening/adopsi).
 *
 * Akun tidak pernah dihapus (jejak audit dan riwayat tetap utuh), melainkan dinonaktifkan. Akun Super Admin
 * tidak dapat diubah lewat API ini.
 */
class SuperAdminController extends Controller
{
    public function __construct(
        private readonly AkunPengurusService $akun,
        private readonly PengirimanNotifikasiService $pengiriman,
    ) {
    }

    /** Matriks hak akses per role (tetap, ditentukan di kode) beserta jumlah akunnya. */
    public function hakAkses(): JsonResponse
    {
        $jumlah = User::selectRaw('role, count(*) as jumlah')->groupBy('role')->pluck('jumlah', 'role');
        $keterangan = [
            User::ROLE_SUPERADMIN => 'Aspek teknis sistem: akun pengurus, hak akses, dan audit log. Tidak memutuskan screening/adopsi.',
            User::ROLE_ADMIN      => 'Operasional harian Jaga Satwa: kucing, feeding, kesehatan, adopsi, serah terima, pemantauan, artikel, notifikasi.',
            User::ROLE_ADOPTER    => 'Pemilik akun setelah serah terima: menerima pengingat pemantauan.',
        ];

        return response()->json([
            'success' => true,
            'data'    => array_map(fn (string $role) => [
                'role'        => $role,
                'role_label'  => User::ROLE_LABEL[$role],
                'keterangan'  => $keterangan[$role],
                'akses'       => User::AKSES[$role],
                'jumlah_akun' => (int) ($jumlah[$role] ?? 0),
            ], User::ROLES),
            'meta'    => ['catatan' => 'Hak akses melekat pada role (RBAC) dan tidak diubah per akun.'],
        ]);
    }

    /** Daftar semua akun (pengurus dan adopter) beserta jumlah per role/status untuk lencana. */
    public function index(FilterUserRequest $request): JsonResponse
    {
        $f = $request->validated();

        $halaman = $this->kueri()
            ->when($f['role'] ?? null, fn (Builder $q, $v) => $q->where('role', $v))
            ->when($f['status'] ?? null, fn (Builder $q, $v) => $q->where('status_akun', $v))
            ->when(trim((string) ($f['q'] ?? '')) !== '', function (Builder $q) use ($f) {
                $like = '%'.addcslashes(trim((string) $f['q']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('nama', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when(($f['sort'] ?? 'terbaru') === 'nama', fn (Builder $q) => $q->orderBy('nama'), fn (Builder $q) => $q->orderByDesc('id_user'))
            ->paginate($f['per_page'] ?? 20, ['*'], 'page', $f['page'] ?? 1);

        $perRole = array_fill_keys(User::ROLES, 0);
        foreach (User::selectRaw('role, count(*) as jumlah')->groupBy('role')->pluck('jumlah', 'role') as $role => $jumlah) {
            $perRole[$role] = (int) $jumlah;
        }
        $perStatus = array_fill_keys(User::STATUS_AKUN, 0);
        foreach (User::selectRaw('status_akun, count(*) as jumlah')->groupBy('status_akun')->pluck('jumlah', 'status_akun') as $status => $jumlah) {
            $perStatus[$status] = (int) $jumlah;
        }

        return response()->json([
            'success' => true,
            'data'    => UserAkunResource::collection($halaman->getCollection())->resolve(),
            'meta'    => [
                'halaman'           => $halaman->currentPage(),
                'halaman_akhir'     => $halaman->lastPage(),
                'per_halaman'       => $halaman->perPage(),
                'total'             => $halaman->total(),
                'jumlah_per_role'   => $perRole,
                'jumlah_per_status' => $perStatus,
            ],
        ]);
    }

    /** Detail akun: sesi aktif, 10 aktivitas terakhir, dan status undangan/reset terakhir (tanpa isi pesan). */
    public function show(int $id): JsonResponse
    {
        $user = $this->kueri()->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->detail($user)]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        [$user, $undangan] = $this->akun->buatAdmin($request->validated('nama'), $request->validated('email'));

        // Kirim langsung agar pemilik segera menerima tautan; kegagalan SMTP tidak menggagalkan pembuatan akun.
        $undangan = $this->pengiriman->kirimEmail($undangan->load('user'));

        return response()->json([
            'success' => true,
            'message' => $undangan->status_pengiriman === Notifikasi::STATUS_TERKIRIM
                ? 'Akun admin dibuat. Tautan aktivasi sudah dikirim ke emailnya.'
                : 'Akun admin dibuat, tetapi email undangan gagal dikirim. Kirim ulang lewat reset-password.',
            'data'    => (new UserAkunResource($user))->resolve() + ['undangan' => $this->ringkasUndangan($undangan)],
        ], 201);
    }

    /** Ubah nama/email admin. Mengganti email akun yang belum diaktifkan otomatis mengirim undangan baru ke email itu. */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($tolak = $this->tolakBukanAdmin($user)) {
            return $tolak;
        }

        $user->fill($request->validated());
        $emailBerubah = $user->isDirty('email');
        $user->save();

        $undangan = null;
        if ($emailBerubah) {
            // Mengganti email dianggap perubahan kredensial: sesi lama dicabut. Akun yang belum diaktifkan
            // menerima undangan baru di email barunya (tautan lama terikat ke email lama).
            $user->tokens()->delete();
            if ($user->status_akun === User::STATUS_MENUNGGU_AKTIVASI) {
                $undangan = $this->pengiriman->kirimEmail($this->akun->undang($user, Notifikasi::JENIS_UNDANGAN_PENGURUS)->load('user'));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data akun berhasil diperbarui.',
            'data'    => (new UserAkunResource($user->refresh()))->resolve() + ($undangan ? ['undangan' => $this->ringkasUndangan($undangan)] : []),
        ]);
    }

    /** Aktifkan atau tangguhkan akun. Penangguhan langsung mengeluarkan semua sesi login. */
    public function ubahStatus(UbahStatusUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $baru = $request->validated('status');

        if ($tolak = $this->tolakSuperAdmin($user)) {
            return $tolak;
        }
        if ($baru === 'aktif' && $user->status_akun === User::STATUS_MENUNGGU_AKTIVASI) {
            return $this->tolak('Akun belum diaktifkan pemiliknya. Pemilik mengaktifkan akun lewat tautan di emailnya; kirim ulang lewat reset-password bila perlu.');
        }

        if ($user->status_akun === $baru) {
            // FormRequest adalah salinan request, jadi penanda harus diset pada request aktif yang dibaca middleware audit.
            request()->attributes->set(RecordAuditLog::LEWATI, true);   // tidak ada yang berubah

            return response()->json([
                'success' => true,
                'message' => "Akun sudah berstatus {$baru}.",
                'data'    => (new UserAkunResource($user))->resolve(),
            ]);
        }

        $this->akun->ubahStatus($user, $baru);

        return response()->json([
            'success' => true,
            'message' => $baru === 'nonaktif' ? 'Akun ditangguhkan dan semua sesi loginnya dikeluarkan.' : 'Akun diaktifkan kembali.',
            'data'    => (new UserAkunResource($user->refresh()))->resolve(),
        ]);
    }

    /**
     * Reset kredensial: sesi login dicabut, akun kembali menunggu aktivasi, dan tautan baru dikirim ke emailnya.
     * Super Admin tidak pernah melihat atau menentukan password.
     */
    public function resetPassword(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($tolak = $this->tolakSuperAdmin($user)) {
            return $tolak;
        }
        if ($user->status_akun === 'nonaktif') {
            return $this->tolak('Akun sedang ditangguhkan. Aktifkan kembali dulu sebelum mereset passwordnya.');
        }

        $undangan = $this->pengiriman->kirimEmail($this->akun->resetPassword($user)->load('user'));

        return response()->json([
            'success' => true,
            'message' => $undangan->status_pengiriman === Notifikasi::STATUS_TERKIRIM
                ? 'Sesi login dicabut dan tautan atur password baru dikirim ke emailnya.'
                : 'Sesi login dicabut, tetapi email gagal dikirim. Coba reset lagi beberapa saat lagi.',
            'data'    => (new UserAkunResource($user->refresh()))->resolve() + ['undangan' => $this->ringkasUndangan($undangan)],
        ]);
    }

    /** Daftar akun + waktu login terakhir (subkueri satu indeks, tanpa N+1). */
    private function kueri(): Builder
    {
        return User::query()->addSelect([
            'users.*',
            'login_terakhir' => AuditLog::select('waktu_aktivitas')
                ->whereColumn('audit_log.id_user', 'users.id_user')->where('aktivitas', 'LOGIN')
                ->orderByDesc('waktu_aktivitas')->limit(1),
        ]);
    }

    /** @return array<string, mixed> */
    private function detail(User $user): array
    {
        $undangan = Notifikasi::where('id_user', $user->id_user)->whereIn('jenis_notifikasi', [Notifikasi::JENIS_UNDANGAN_PENGURUS, Notifikasi::JENIS_RESET_PASSWORD])
            ->orderByDesc('id_notifikasi')->first();

        return (new UserAkunResource($user))->resolve() + [
            'sesi_aktif'          => $user->tokens()->count(),
            'undangan_terakhir'   => $undangan ? $this->ringkasUndangan($undangan) : null,
            'aktivitas_terakhir'  => AuditLog::where('id_user', $user->id_user)->orderByDesc('waktu_aktivitas')->orderByDesc('id_audit')->limit(10)->get()
                ->map(fn (AuditLog $a) => [
                    'waktu_aktivitas' => $a->waktu_aktivitas?->copy()->setTimezone('Asia/Jakarta')->toIso8601String(),
                    'aktivitas'       => $a->aktivitas,
                    'nama_tabel'      => $a->nama_tabel,
                    'id_data'         => $a->id_data,
                ])->all(),
        ];
    }

    /** Status pengiriman saja; isi pesan (tautan aktivasi) tidak pernah dikembalikan. @return array<string, mixed> */
    private function ringkasUndangan(Notifikasi $n): array
    {
        return [
            'jenis'             => $n->jenis_notifikasi,
            'status_pengiriman' => $n->status_pengiriman,
            'tanggal_dikirim'   => $n->tanggal_dikirim?->format('Y-m-d'),
            'catatan_galat'     => $n->catatan_galat,
            'dibuat_pada'       => $n->created_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
        ];
    }

    private function tolakSuperAdmin(User $user): ?JsonResponse
    {
        return in_array($user->role, AkunPengurusService::ROLE_DIKELOLA, true)
            ? null
            : $this->tolak('Akun Super Admin tidak dapat diubah lewat API.');
    }

    private function tolakBukanAdmin(User $user): ?JsonResponse
    {
        if ($user->role === User::ROLE_ADOPTER) {
            return $this->tolak('Data adopter dikelola lewat proses adopsi, bukan di sini.');
        }

        return $this->tolakSuperAdmin($user);
    }

    private function tolak(string $pesan): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $pesan], 422);
    }
}

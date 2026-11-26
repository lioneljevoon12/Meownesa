<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSerahTerimaRequest;
use App\Http\Requests\UploadBerkasSerahTerimaRequest;
use App\Http\Resources\SerahTerimaResource;
use App\Models\PengajuanAdopsi;
use App\Models\SerahTerima;
use App\Models\User;
use App\Services\AktivasiAkunService;
use App\Services\AuditLogger;
use App\Services\NotifikasiPengajuanService;
use App\Services\SerahTerimaBerkasService;
use App\Services\SerahTerimaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Serah terima kucing dan pembuatan akun adopter (dev-backend-09, MEOW-BR-06, BR-07). Route: role:admin.
 * Akun adopter TIDAK dibuat saat mengajukan, melainkan otomatis di langkah serah terima ini.
 */
class SerahTerimaController extends Controller
{
    public function __construct(
        private readonly SerahTerimaService $serahTerima,
        private readonly SerahTerimaBerkasService $berkas,
        private readonly AktivasiAkunService $aktivasi,
        private readonly NotifikasiPengajuanService $notifikasi,
    ) {
    }

    public function store(StoreSerahTerimaRequest $request, int $pengajuan): JsonResponse
    {
        [$st, $efek] = $this->serahTerima->catat(
            $pengajuan,
            $request->safe()->only(['tanggal_serah_terima']),
            $request->file('dokumen_perjanjian'),
            $request->file('foto_serah_terima'),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Serah terima berhasil dicatat. Kucing kini berstatus Diadopsi.',
            'data'    => (new SerahTerimaResource($this->detail($st->id_serah_terima)))->resolve() + ['efek' => $efek],
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new SerahTerimaResource($this->detail($id))]);
    }

    /** Unduh BAST atau foto serah terima. Hanya admin; setiap akses diaudit (data pribadi). */
    public function berkas(Request $request, int $id, string $jenis): StreamedResponse
    {
        $st = SerahTerima::findOrFail($id);
        $path = $st->{$jenis};   // jenis sudah dibatasi route (whereIn) ke dua kolom berkas

        abort_unless($path && Storage::disk(SerahTerimaBerkasService::DISK)->exists($path), 404, 'Berkas belum diunggah.');

        AuditLogger::record($request->user(), 'LIHAT_BERKAS_SERAH_TERIMA', 'serah_terima', $st->id_serah_terima);

        $ekstensi = pathinfo($path, PATHINFO_EXTENSION);
        $nama = 'serah-terima-'.$st->id_serah_terima.'-'.Str::slug(str_replace('_', ' ', $jenis)).'.'.$ekstensi;

        return Storage::disk(SerahTerimaBerkasService::DISK)->response(
            $path,
            $nama,
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
            $ekstensi === 'pdf' ? 'attachment' : 'inline',
        );
    }

    /** Unggah atau ganti berkas belakangan; berkas lama dihapus dari disk. */
    public function uploadBerkas(UploadBerkasSerahTerimaRequest $request, int $id): JsonResponse
    {
        $jenis = $request->input('jenis');
        $baru = null;
        $lama = null;

        try {
            DB::transaction(function () use ($request, $id, $jenis, &$baru, &$lama) {
                $st = SerahTerima::whereKey($id)->lockForUpdate()->firstOrFail();
                $lama = $st->{$jenis};
                $baru = $this->berkas->simpan($st, $jenis, $request->file('berkas'));
                $st->update([$jenis => $baru]);
            });
        } catch (Throwable $e) {
            $this->berkas->hapus($baru);   // transaksi gagal: buang berkas baru agar tidak yatim
            throw $e;
        }

        $this->berkas->hapus($lama);

        return response()->json([
            'success' => true,
            'message' => 'Berkas berhasil diunggah.',
            'data'    => new SerahTerimaResource($this->detail($id)),
        ], 201);
    }

    /**
     * Kirim ulang tautan aktivasi akun (tautan lama kedaluwarsa atau hilang). Hanya selagi akun belum
     * diaktifkan; tautan lama otomatis tidak berlaku. Token tidak dikembalikan ke admin.
     */
    public function kirimUlangAktivasi(int $pengajuan): JsonResponse
    {
        $p = PengajuanAdopsi::with('adopter.user')->findOrFail($pengajuan);
        $user = $p->adopter?->user;

        if ($p->status_pengajuan !== 'SELESAI' || ! $user || $user->status_akun !== User::STATUS_MENUNGGU_AKTIVASI) {
            throw ValidationException::withMessages([
                'status' => 'Tautan aktivasi hanya dapat dikirim ulang untuk adopter yang akunnya belum diaktifkan (setelah serah terima dicatat).',
            ]);
        }

        $this->notifikasi->aktivasiAkun($p, $this->aktivasi->buatTautan($user));

        return response()->json([
            'success' => true,
            'message' => 'Tautan aktivasi baru sudah masuk antrean notifikasi (WhatsApp dan Email). Tautan sebelumnya tidak berlaku lagi.',
            'data'    => ['id_pengajuan' => $p->id_pengajuan],
        ]);
    }

    private function detail(int $id): SerahTerima
    {
        return SerahTerima::query()->with(['pengajuanAdopsi.kucing', 'pengajuanAdopsi.adopter.user'])->findOrFail($id);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjukanAdopsiRequest;
use App\Http\Requests\UploadLampiranRequest;
use App\Http\Resources\PengajuanPublikResource;
use App\Models\Kucing;
use App\Models\PemberianMakan;
use App\Models\PengajuanAdopsi;
use App\Services\LampiranService;
use App\Services\TrackingCodeGenerator;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pengajuan adopsi PUBLIK tanpa akun (MEOW-F-14, F-15, F-20, MEOW-BR-03, BR-04, BR-14).
 *
 * Endpoint ini terbuka untuk umum, jadi dijaga dengan: rate limit per IP, kode tracking acak berentropi
 * tinggi sebagai satu-satunya kunci akses ke sebuah pengajuan, lampiran privat, dan respons yang tidak
 * pernah memuat data pribadi (lihat PengajuanPublikResource).
 */
class PengajuanAdopsiController extends Controller
{
    public function __construct(
        private readonly TrackingCodeGenerator $kode,
        private readonly LampiranService $lampiran,
    ) {
    }

    public function ajukan(AjukanAdopsiRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['id_kucing', 'nama_pemohon', 'nomor_whatsapp', 'email', 'alamat']);
        $berkas = array_filter([
            'KTP'               => $request->file('ktp'),
            'Foto Rumah'        => $request->file('foto_rumah'),
            'Dokumen Pendukung' => $request->file('dokumen_pendukung'),
        ]);

        $tersimpan = [];

        try {
            $pengajuan = DB::transaction(function () use ($data, $berkas, &$tersimpan) {
                // Baris kucing dikunci: pengecekan status & duplikat tidak bisa diterobos permintaan bersamaan.
                $kucing = Kucing::whereKey($data['id_kucing'])->lockForUpdate()->firstOrFail();

                if ($kucing->status_kucing !== 'Tersedia') {
                    throw ValidationException::withMessages([
                        'id_kucing' => 'Kucing ini belum tersedia untuk diadopsi. Pengajuan hanya dapat dilakukan pada kucing berstatus "Siap Diadopsi".',
                    ]);
                }

                $sudahAda = PengajuanAdopsi::where('id_kucing', $kucing->id_kucing)
                    ->whereIn('status_pengajuan', PengajuanAdopsi::STATUS_AKTIF)
                    ->where(fn ($q) => $q->where('email', $data['email'])->orWhere('nomor_whatsapp', $data['nomor_whatsapp']))
                    ->exists();

                if ($sudahAda) {
                    // Nomor pengajuan sengaja tidak disebut: pesan ini tidak boleh membocorkan data orang lain.
                    throw ValidationException::withMessages([
                        'email' => 'Anda sudah memiliki pengajuan yang masih berjalan untuk kucing ini. Gunakan kode tracking yang Anda terima saat mengajukan untuk memeriksa statusnya.',
                    ]);
                }

                $pengajuan = $this->buat($data);

                foreach ($berkas as $jenis => $file) {
                    $tersimpan[] = $this->lampiran->simpan($pengajuan, $jenis, $file)->url_file;
                }

                return $pengajuan;
            });
        } catch (Throwable $e) {
            foreach ($tersimpan as $path) {
                $this->lampiran->hapusBerkas($path);   // jangan tinggalkan berkas yatim bila transaksi dibatalkan
            }
            throw $e;
        }

        $pengajuan->load(['kucing.fotoUtama', 'lampiranPengajuan']);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan berhasil dikirim. Simpan nomor pengajuan dan kode tracking Anda untuk memeriksa status kapan saja.',
            'data'    => (new PengajuanPublikResource($pengajuan))->resolve() + ['kode_tracking' => $pengajuan->kode_tracking],
        ], 201);
    }

    public function tracking(Request $request, string $kode): JsonResponse
    {
        $request->validate(['nomor_pengajuan' => ['nullable', 'string', 'max:30']]);

        $pengajuan = $this->cari($kode);

        // Nomor pengajuan bersifat opsional sebagai pemeriksaan tambahan (proposal 2.1.2: cek status dengan
        // nomor + kode). Salah nomor diperlakukan persis sama dengan kode tidak dikenal.
        if ($pengajuan && $request->filled('nomor_pengajuan')
            && ! hash_equals($pengajuan->nomor_pengajuan, strtoupper(trim($request->input('nomor_pengajuan'))))) {
            $pengajuan = null;
        }

        if (! $pengajuan) {
            return $this->tidakDitemukan();
        }

        $pengajuan->load(['kucing.fotoUtama', 'lampiranPengajuan']);

        return response()->json(['success' => true, 'data' => new PengajuanPublikResource($pengajuan)]);
    }

    public function uploadLampiran(UploadLampiranRequest $request): JsonResponse
    {
        $pengajuan = $this->cari($request->input('kode_tracking'));
        if (! $pengajuan) {
            return $this->tidakDitemukan();
        }

        $path = null;

        try {
            $lampiran = DB::transaction(function () use ($request, $pengajuan, &$path) {
                $p = PengajuanAdopsi::whereKey($pengajuan->id_pengajuan)->lockForUpdate()->firstOrFail();

                if (! in_array($p->status_pengajuan, PengajuanAdopsi::STATUS_AKTIF, true)) {
                    throw ValidationException::withMessages([
                        'kode_tracking' => 'Pengajuan ini sudah diputuskan, sehingga lampiran tidak dapat ditambah lagi.',
                    ]);
                }

                if ($p->lampiranPengajuan()->count() >= LampiranService::MAKS_PER_PENGAJUAN) {
                    throw ValidationException::withMessages([
                        'berkas' => 'Maksimal '.LampiranService::MAKS_PER_PENGAJUAN.' lampiran per pengajuan.',
                    ]);
                }

                $lampiran = $this->lampiran->simpan($p, $request->input('jenis_lampiran'), $request->file('berkas'));
                $path = $lampiran->url_file;

                return $lampiran;
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                $this->lampiran->hapusBerkas($path);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Lampiran berhasil diunggah.',
            'data'    => [
                'id_lampiran'    => $lampiran->id_lampiran,
                'jenis_lampiran' => $lampiran->jenis_lampiran,
                'jumlah_lampiran' => $pengajuan->lampiranPengajuan()->count(),
            ],
        ], 201);
    }

    /**
     * Buat baris pengajuan dengan nomor dan kode baru. Keunikan dijaga constraint UNIQUE; bila dua
     * pengajuan bersamaan mengambil nomor yang sama, ulangi dengan nomor berikutnya.
     *
     * @param  array<string, mixed>  $data
     */
    private function buat(array $data): PengajuanAdopsi
    {
        for ($percobaan = 1; ; $percobaan++) {
            try {
                return PengajuanAdopsi::create($data + [
                    'nomor_pengajuan'   => $this->kode->nomorPengajuan(),
                    'kode_tracking'     => $this->kode->kodeTracking(),
                    'status_pengajuan'  => 'MENUNGGU',
                    'tanggal_pengajuan' => Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($percobaan >= 5) {
                    throw $e;
                }
            }
        }
    }

    private function cari(string $kode): ?PengajuanAdopsi
    {
        return PengajuanAdopsi::where('kode_tracking', strtoupper(trim($kode)))->first();
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Pengajuan tidak ditemukan. Periksa kembali kode tracking Anda.',
        ], 404);
    }
}

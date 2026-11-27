<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterNotifikasiRequest;
use App\Http\Requests\KirimNotifikasiRequest;
use App\Http\Requests\TandaiNotifikasiRequest;
use App\Http\Resources\NotifikasiResource;
use App\Models\Notifikasi;
use App\Services\PengingatPascaAdopsiService;
use App\Services\PengirimanNotifikasiService;
use Illuminate\Http\JsonResponse;

/**
 * Notifikasi dan pengingat (MEOW-F-30 s/d F-32, MEOW-BR-12). Route: role:admin + audit:notifikasi.
 *
 * Email dikirim sistem; WhatsApp hanya satu arah dan manual lewat tautan wa.me (tanpa gateway berbayar).
 */
class NotifikasiController extends Controller
{
    public function __construct(
        private readonly PengirimanNotifikasiService $pengiriman,
        private readonly PengingatPascaAdopsiService $pengingat,
    ) {
    }

    /** Histori notifikasi, terbaru di atas, beserta jumlah per status untuk lencana tab. */
    public function index(FilterNotifikasiRequest $request): JsonResponse
    {
        $f = $request->validated();
        $perHalaman = $f['per_page'] ?? 20;

        $halaman = Notifikasi::with('pengajuanAdopsi')
            ->filter($f)
            ->orderByDesc('id_notifikasi')
            ->paginate($perHalaman, ['*'], 'page', $f['page'] ?? 1);

        // Jumlah per status mengikuti filter kanal/jenis/pengajuan, tetapi tidak filter status itu sendiri.
        $perStatus = array_fill_keys(Notifikasi::STATUS, 0);
        $hitung = Notifikasi::filter(array_diff_key($f, ['status' => 1]))
            ->selectRaw('status_pengiriman, count(*) as jumlah')->groupBy('status_pengiriman')->pluck('jumlah', 'status_pengiriman');
        foreach ($hitung as $status => $jumlah) {
            $perStatus[$status] = (int) $jumlah;
        }

        return response()->json([
            'success' => true,
            'data'    => NotifikasiResource::collection($halaman->getCollection())->resolve(),
            'meta'    => [
                'halaman'           => $halaman->currentPage(),
                'halaman_akhir'     => $halaman->lastPage(),
                'per_halaman'       => $halaman->perPage(),
                'total'             => $halaman->total(),
                'jumlah_per_status' => $perStatus,
            ],
        ]);
    }

    /**
     * Kirim email: satu notifikasi (PENDING atau GAGAL, untuk mencoba ulang) bila `id_notifikasi` diberikan,
     * atau semua email PENDING bila tidak.
     */
    public function kirim(KirimNotifikasiRequest $request): JsonResponse
    {
        $id = $request->validated('id_notifikasi');

        if ($id === null) {
            $hasil = $this->pengiriman->kirimTertunda();

            return response()->json([
                'success' => true,
                'message' => $hasil['diproses'] === 0
                    ? 'Tidak ada email yang menunggu dikirim.'
                    : "Email diproses: {$hasil['terkirim']} terkirim, {$hasil['gagal']} gagal.",
                'data'    => $hasil,
            ]);
        }

        $n = Notifikasi::with('pengajuanAdopsi')->findOrFail($id);

        if ($n->kanal !== Notifikasi::KANAL_EMAIL) {
            return $this->tolak('Pesan WhatsApp dikirim manual lewat tautan wa.me, lalu tandai hasilnya.');
        }
        if ($n->status_pengiriman === Notifikasi::STATUS_TERKIRIM) {
            return $this->tolak('Email ini sudah terkirim.');
        }

        $n = $this->pengiriman->kirimEmail($n);

        return response()->json([
            'success' => true,
            'message' => $n->status_pengiriman === Notifikasi::STATUS_TERKIRIM
                ? 'Email berhasil dikirim.'
                : 'Email gagal dikirim: '.$n->catatan_galat,
            'data'    => (new NotifikasiResource($n))->resolve(),
        ]);
    }

    /** Admin melaporkan hasil pengiriman WhatsApp manual. */
    public function tandai(TandaiNotifikasiRequest $request, int $id): JsonResponse
    {
        $n = Notifikasi::with('pengajuanAdopsi')->findOrFail($id);

        if ($n->kanal !== Notifikasi::KANAL_WHATSAPP) {
            return $this->tolak('Status email diatur otomatis oleh sistem.');
        }
        if ($n->status_pengiriman === Notifikasi::STATUS_TERKIRIM) {
            return $this->tolak('Pesan ini sudah ditandai terkirim.');
        }

        $n = $this->pengiriman->tandaiWhatsApp($n, $request->validated('status'), $request->validated('catatan'));

        return response()->json([
            'success' => true,
            'message' => $n->status_pengiriman === Notifikasi::STATUS_TERKIRIM ? 'Pesan WhatsApp ditandai terkirim.' : 'Pesan WhatsApp ditandai gagal.',
            'data'    => (new NotifikasiResource($n))->resolve(),
        ]);
    }

    /** Buat pengingat kabar kondisi kucing yang sudah jatuh tempo (sama dengan perintah terjadwal) dan kirim emailnya. */
    public function pengingat(): JsonResponse
    {
        $hasil = $this->pengingat->jalankan();
        $email = $this->pengiriman->kirimTertunda();

        return response()->json([
            'success' => true,
            'message' => $hasil['pengingat_dibuat'] === 0
                ? 'Tidak ada pengingat baru yang perlu dibuat.'
                : "{$hasil['pengingat_dibuat']} pengingat dibuat. Kirim yang WhatsApp lewat tautan wa.me di histori notifikasi.",
            'data'    => $hasil + ['email' => $email],
        ]);
    }

    private function tolak(string $pesan): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $pesan], 422);
    }
}

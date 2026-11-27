<?php

namespace App\Services;

use App\Models\Kucing;
use App\Models\Notifikasi;
use App\Models\PemberianMakan;
use App\Models\PengajuanAdopsi;
use Carbon\Carbon;

/**
 * Statistik ringkas untuk dashboard admin (MEOW-F-38 s/d F-40, MEOW-NF-09).
 *
 * Semua angka dihitung langsung dari tabel sumber (tanpa cache) lewat kueri agregat (COUNT/GROUP BY),
 * bukan memuat baris satu per satu, sehingga tetap cepat. Tanggal "hari ini" menurut WIB.
 */
class DashboardService
{
    /** Pengajuan MENUNGGU yang belum disentuh lebih dari ini dianggap perlu perhatian. */
    public const BATAS_HARI_MENUNGGU = 3;

    /** Pengajuan DISETUJUI yang belum diserahterimakan lebih dari ini dianggap tertunda. */
    public const BATAS_HARI_SERAH_TERIMA = 7;

    private const URUTAN_LEVEL = ['penting' => 0, 'peringatan' => 1, 'info' => 2];

    public function __construct(private readonly PemantauanPascaAdopsiService $pemantauan)
    {
    }

    /** @return array<string, mixed> */
    public function statistik(?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? Carbon::now())->copy()->setTimezone(PemberianMakan::ZONA_WAKTU);
        $hariIni = $sekarang->toDateString();

        $perStatus = $this->hitungKucingPerStatus();
        $diKampus = array_sum(array_intersect_key($perStatus, array_flip(Kucing::STATUS_DI_KAMPUS)));

        $pengajuan = PengajuanAdopsi::whereIn('status_pengajuan', ['MENUNGGU', 'SCREENING', 'DISETUJUI'])
            ->selectRaw('status_pengajuan, count(*) as jumlah')->groupBy('status_pengajuan')->pluck('jumlah', 'status_pengajuan');
        $menunggu = (int) ($pengajuan['MENUNGGU'] ?? 0);
        $screening = (int) ($pengajuan['SCREENING'] ?? 0);
        $disetujui = (int) ($pengajuan['DISETUJUI'] ?? 0);

        $feeding = $this->hitungFeeding($hariIni);
        $steril = $this->hitungSteril($diKampus);

        $pemantauan = $this->pemantauan->semua($sekarang)->countBy('status_pemantauan');
        $terlambat = (int) ($pemantauan['terlambat'] ?? 0);
        $aktif = (int) ($pemantauan['aktif'] ?? 0);

        return [
            'tanggal'         => $hariIni,
            'diperbarui_pada' => $sekarang->toIso8601String(),
            'metrik'          => [
                'total_kucing' => [
                    'nilai'      => $diKampus,
                    'keterangan' => 'Kucing yang masih di area kampus (Terdata, Tersedia, Proses Screening).',
                    'per_status' => $perStatus,
                ],
                'siap_adopsi' => [
                    'nilai' => $perStatus['Tersedia'],
                ],
                'antrean_review' => [
                    'nilai'                => $menunggu + $screening,
                    'menunggu'             => $menunggu,
                    'screening'            => $screening,
                    'menunggu_serah_terima' => $disetujui,
                ],
                'feeding_hari_ini' => ['nilai' => $feeding['Tuntas']] + [
                    'tuntas'    => $feeding['Tuntas'],
                    'belum'     => $feeding['Belum'],
                    'terjadwal' => $feeding['Terjadwal'],
                    'total'     => array_sum($feeding),
                ],
                'persentase_sterilisasi' => $steril,
                'pengingat_terlambat' => [
                    'nilai'            => $terlambat,
                    'pemantauan_aktif' => $aktif + $terlambat,
                ],
            ],
            'peringatan' => $this->peringatan($sekarang, $feeding['Belum'], $terlambat),
        ];
    }

    /**
     * Pengajuan yang menunggu tindakan admin, yang paling perlu ditangani di atas: MENUNGGU (belum dimulai),
     * lalu SCREENING (menunggu keputusan), lalu DISETUJUI (menunggu serah terima); masing-masing yang terlama dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function antreanTerbaru(int $batas = 5, ?Carbon $sekarang = null): array
    {
        $hariIni = ($sekarang ?? Carbon::now())->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->startOfDay();

        return PengajuanAdopsi::query()
            ->with('kucing:id_kucing,kode_kucing,nama_kucing')
            ->withExists('screeningAdopsi as sudah_discreening')
            ->whereIn('status_pengajuan', ['MENUNGGU', 'SCREENING', 'DISETUJUI'])
            ->orderByRaw("CASE status_pengajuan WHEN 'MENUNGGU' THEN 0 WHEN 'SCREENING' THEN 1 ELSE 2 END")
            ->orderBy('tanggal_pengajuan')->orderBy('id_pengajuan')
            ->limit($batas)
            ->get()
            ->map(function (PengajuanAdopsi $p) use ($hariIni) {
                $diajukan = Carbon::createFromFormat('Y-m-d', $p->tanggal_pengajuan->format('Y-m-d'), PemberianMakan::ZONA_WAKTU)->startOfDay();

                return [
                    'id_pengajuan'       => $p->id_pengajuan,
                    'nomor_pengajuan'    => $p->nomor_pengajuan,
                    'nama_pemohon'       => $p->nama_pemohon,
                    'kucing'             => $p->kucing ? [
                        'id_kucing'   => $p->kucing->id_kucing,
                        'kode_kucing' => $p->kucing->kode_kucing,
                        'nama_kucing' => $p->kucing->nama_kucing,
                    ] : null,
                    'status_pengajuan'   => $p->status_pengajuan,
                    'tanggal_pengajuan'  => $diajukan->toDateString(),
                    'hari_sejak_diajukan' => max(0, (int) $diajukan->diffInDays($hariIni)),
                    'tindakan_berikutnya' => match ($p->status_pengajuan) {
                        'MENUNGGU'  => 'Mulai pemeriksaan',
                        'SCREENING' => $p->sudah_discreening ? 'Putuskan: setujui atau tolak' : 'Isi hasil screening',
                        default     => 'Catat serah terima',
                    },
                ];
            })->all();
    }

    /** @return array<string, int> semua status kucing, 0 bila kosong */
    private function hitungKucingPerStatus(): array
    {
        $hasil = array_fill_keys(Kucing::STATUS, 0);
        foreach (Kucing::selectRaw('status_kucing, count(*) as jumlah')->groupBy('status_kucing')->pluck('jumlah', 'status_kucing') as $status => $jumlah) {
            $hasil[$status] = (int) $jumlah;
        }

        return $hasil;
    }

    /** @return array<string, int> */
    private function hitungFeeding(string $hariIni): array
    {
        $hasil = array_fill_keys(PemberianMakan::STATUS, 0);
        foreach (PemberianMakan::where('tanggal', $hariIni)->selectRaw('status_pemberian, count(*) as jumlah')->groupBy('status_pemberian')->pluck('jumlah', 'status_pemberian') as $status => $jumlah) {
            $hasil[$status] = (int) $jumlah;
        }

        return $hasil;
    }

    /**
     * Persentase kucing di kampus yang sudah steril. Yang berstatus "Tidak Diketahui" ikut dihitung
     * sebagai belum terkonfirmasi, jadi angkanya konservatif, tidak melebih-lebihkan capaian.
     *
     * @return array<string, mixed>
     */
    private function hitungSteril(int $diKampus): array
    {
        $per = Kucing::whereIn('status_kucing', Kucing::STATUS_DI_KAMPUS)
            ->selectRaw('status_steril, count(*) as jumlah')->groupBy('status_steril')->pluck('jumlah', 'status_steril');
        $sudah = (int) ($per['Sudah'] ?? 0);

        return [
            'nilai'           => $diKampus > 0 ? round($sudah / $diKampus * 100, 1) : 0.0,
            'sudah'           => $sudah,
            'belum'           => (int) ($per['Belum'] ?? 0),
            'tidak_diketahui' => (int) ($per['Tidak Diketahui'] ?? 0),
            'total'           => $diKampus,
        ];
    }

    /** @return array<int, array<string, mixed>> hanya yang jumlahnya > 0, urut dari yang paling penting */
    private function peringatan(Carbon $sekarang, int $feedingBelum, int $terlambat): array
    {
        $zonaApp = config('app.timezone');

        $menungguLama = PengajuanAdopsi::where('status_pengajuan', 'MENUNGGU')
            ->whereDate('tanggal_pengajuan', '<=', $sekarang->copy()->subDays(self::BATAS_HARI_MENUNGGU)->toDateString())->count();
        $serahTerimaTertunda = PengajuanAdopsi::where('status_pengajuan', 'DISETUJUI')
            ->where('updated_at', '<=', $sekarang->copy()->subDays(self::BATAS_HARI_SERAH_TERIMA)->setTimezone($zonaApp))->count();
        $emailGagal = Notifikasi::untukAdmin()->where('kanal', Notifikasi::KANAL_EMAIL)->where('status_pengiriman', Notifikasi::STATUS_GAGAL)->count();
        $laporanMenunggu = \App\Models\LaporanAdopter::where('status_laporan', \App\Models\LaporanAdopter::MENUNGGU)->count();
        $waMenunggu = Notifikasi::untukAdmin()->where('kanal', Notifikasi::KANAL_WHATSAPP)->where('status_pengiriman', Notifikasi::STATUS_PENDING)->count();

        $daftar = [
            ['email_gagal', 'penting', "{$emailGagal} email notifikasi gagal terkirim.", $emailGagal, 'admin/notifikasi?kanal=Email&status=GAGAL'],
            ['pengajuan_menunggu_lama', 'peringatan', "{$menungguLama} pengajuan menunggu pemeriksaan lebih dari ".self::BATAS_HARI_MENUNGGU.' hari.', $menungguLama, 'admin/adopsi?status=MENUNGGU&sort=terlama'],
            ['pengingat_terlambat', 'peringatan', "{$terlambat} adopter terlambat memberi kabar kondisi kucing.", $terlambat, 'admin/pasca-adopsi?status=terlambat'],
            ['feeding_belum_diberi', 'peringatan', "{$feedingBelum} kucing belum diberi makan hari ini.", $feedingBelum, 'admin/feeding?status=Belum&tanggal='.$sekarang->toDateString()],
            ['serah_terima_tertunda', 'info', "{$serahTerimaTertunda} adopsi disetujui lebih dari ".self::BATAS_HARI_SERAH_TERIMA.' hari tetapi belum diserahterimakan.', $serahTerimaTertunda, 'admin/adopsi?status=DISETUJUI'],
            ['laporan_adopter_menunggu', 'info', "{$laporanMenunggu} laporan kondisi dari adopter menunggu dicatat.", $laporanMenunggu, 'admin/laporan-adopter?status=MENUNGGU'],
            ['whatsapp_menunggu', 'info', "{$waMenunggu} pesan WhatsApp menunggu dikirim manual.", $waMenunggu, 'admin/notifikasi?kanal=WhatsApp&status=PENDING'],
        ];

        $hasil = [];
        foreach ($daftar as [$kode, $level, $pesan, $jumlah, $rujukan]) {
            if ($jumlah > 0) {
                $hasil[] = compact('kode', 'level', 'pesan', 'jumlah', 'rujukan');
            }
        }

        usort($hasil, fn (array $a, array $b) => self::URUTAN_LEVEL[$a['level']] <=> self::URUTAN_LEVEL[$b['level']]);

        return $hasil;
    }
}

<?php

namespace App\Services;

use App\Models\Adopter;
use App\Models\Kucing;
use App\Models\PengajuanAdopsi;
use App\Models\SerahTerima;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pencatatan serah terima kucing (MEOW-F-21 s/d F-25, MEOW-BR-06, MEOW-BR-07).
 *
 * Dalam SATU transaksi: serah terima dicatat, kucing menjadi Diadopsi, pengajuan menjadi SELESAI, data dan
 * akun adopter dibuat (atau akun lama dipakai ulang), pengajuan lain untuk kucing itu ditutup, dan
 * notifikasi (dengan tautan aktivasi akun bila perlu) diantrekan. Gagal di mana pun = tidak ada yang berubah.
 */
class SerahTerimaService
{
    public function __construct(
        private readonly SerahTerimaBerkasService $berkas,
        private readonly AktivasiAkunService $aktivasi,
        private readonly NotifikasiPengajuanService $notifikasi,
    ) {
    }

    /**
     * @param  array{tanggal_serah_terima: string}  $data
     * @return array{0: SerahTerima, 1: array<string, mixed>}  [serah terima, ringkasan efek]
     */
    public function catat(int $idPengajuan, array $data, ?UploadedFile $dokumen, ?UploadedFile $foto, User $admin): array
    {
        $tersimpan = [];

        try {
            return DB::transaction(function () use ($idPengajuan, $data, $dokumen, $foto, $admin, &$tersimpan) {
                // Urutan kunci sama dengan PengajuanStatusService: kucing dulu, baru pengajuan.
                $awal = PengajuanAdopsi::findOrFail($idPengajuan);
                $kucing = Kucing::whereKey($awal->id_kucing)->lockForUpdate()->firstOrFail();
                $p = PengajuanAdopsi::whereKey($idPengajuan)->lockForUpdate()->firstOrFail();

                $this->periksa($p, $kucing, $data['tanggal_serah_terima']);

                [$user, $akunBaru] = $this->akunAdopter($p);
                $adopter = Adopter::firstOrCreate(
                    ['id_user' => $user->id_user],
                    ['tanggal_menjadi_adopter' => $data['tanggal_serah_terima'], 'status_adopter' => 'aktif'],
                );

                $st = SerahTerima::create([
                    'id_pengajuan'         => $p->id_pengajuan,
                    'tanggal_serah_terima' => $data['tanggal_serah_terima'],
                    'status_serah_terima'  => SerahTerima::STATUS_SELESAI,
                ]);

                foreach (['dokumen_perjanjian' => $dokumen, 'foto_serah_terima' => $foto] as $kolom => $file) {
                    if ($file) {
                        $st->{$kolom} = $tersimpan[] = $this->berkas->simpan($st, $kolom, $file);
                    }
                }
                $st->save();

                $p->update(['status_pengajuan' => 'SELESAI', 'id_adopter' => $adopter->id_adopter]);
                $kucing->update(['status_kucing' => 'Diadopsi']);   // MEOW-BR-07

                // Pengajuan lain yang masih mengantre tidak akan pernah bisa diproses: kucingnya sudah diadopsi.
                $lain = PengajuanAdopsi::where('id_kucing', $kucing->id_kucing)
                    ->whereKeyNot($p->id_pengajuan)->where('status_pengajuan', 'MENUNGGU')->get();
                foreach ($lain as $l) {
                    $l->update(['status_pengajuan' => 'DITOLAK']);
                    $this->notifikasi->kucingSudahDiadopsi($l);
                }

                // Akun yang belum diaktifkan (baru, atau dari adopsi sebelumnya yang belum dibuka) menerima tautan baru.
                $perluTautan = $akunBaru || $user->status_akun === User::STATUS_MENUNGGU_AKTIVASI;
                $this->notifikasi->serahTerima($p, $perluTautan ? $this->aktivasi->buatTautan($user) : null);

                AuditLogger::record($admin, 'SINKRON_STATUS_KUCING', 'kucing', $kucing->id_kucing);
                if ($akunBaru) {
                    AuditLogger::record($admin, 'BUAT_AKUN_ADOPTER', 'users', $user->id_user);
                }

                return [$st, [
                    'status_kucing'          => 'Diadopsi',
                    'akun_adopter'           => $akunBaru ? 'dibuat' : 'sudah_ada',
                    'menunggu_aktivasi'      => $user->status_akun === User::STATUS_MENUNGGU_AKTIVASI,
                    'pengajuan_lain_ditutup' => $lain->count(),
                ]];
            });
        } catch (Throwable $e) {
            foreach ($tersimpan as $path) {
                $this->berkas->hapus($path);   // jangan tinggalkan berkas yatim bila transaksi dibatalkan
            }
            throw $e;
        }
    }

    private function periksa(PengajuanAdopsi $p, Kucing $kucing, string $tanggal): void
    {
        if ($p->status_pengajuan !== 'DISETUJUI') {
            $this->tolak('status', 'Serah terima hanya dapat dicatat untuk pengajuan yang berstatus DISETUJUI (status saat ini: '.$p->status_pengajuan.').');
        }
        if ($kucing->status_kucing !== 'Proses Screening') {
            $this->tolak('status', "Kucing berstatus {$kucing->status_kucing}, bukan Proses Screening, sehingga serah terima tidak dapat dicatat.");
        }
        if ($tanggal < $p->tanggal_pengajuan->format('Y-m-d')) {
            $this->tolak('tanggal_serah_terima', 'Tanggal serah terima tidak boleh sebelum tanggal pengajuan ('.$p->tanggal_pengajuan->format('Y-m-d').').');
        }
    }

    /**
     * Akun adopter: dibuat baru (menunggu aktivasi) bila email belum terdaftar; dipakai ulang bila email
     * sudah milik adopter (mengadopsi lagi). Email milik akun internal ditolak agar akun admin tidak tertukar.
     *
     * @return array{0: User, 1: bool}  [akun, akun baru?]
     */
    private function akunAdopter(PengajuanAdopsi $p): array
    {
        $user = User::where('email', $p->email)->first();

        if (! $user) {
            return [User::create([
                'nama'          => $p->nama_pemohon,
                'email'         => $p->email,
                'password_hash' => Str::random(64),   // acak dan tidak diketahui siapa pun sampai diaktivasi
                'role'          => User::ROLE_ADOPTER,
                'status_akun'   => User::STATUS_MENUNGGU_AKTIVASI,
            ]), true];
        }

        if ($user->role !== User::ROLE_ADOPTER) {
            $this->tolak('email', 'Email pemohon sudah terdaftar sebagai akun internal Jaga Satwa. Minta pemohon memakai email lain pada pengajuan baru.');
        }

        return [$user, false];
    }

    private function tolak(string $field, string $pesan): never
    {
        throw ValidationException::withMessages([$field => $pesan]);
    }
}

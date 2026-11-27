<?php

namespace App\Services;

use App\Models\Adopter;
use App\Models\LaporanAdopter;
use App\Models\PascaAdopsi;
use App\Models\SerahTerima;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data untuk portal adopter (dev-backend-17): kucing-kucing yang sudah diadopsi akun ini, jadwal kabar berikutnya,
 * riwayat kondisi, dan status laporan yang pernah dikirim.
 *
 * KEPEMILIKAN: seluruh kueri dibatasi ke adopsi milik akun yang login (pengajuan.id_adopter = adopter akun ini).
 * Adopsi orang lain dijawab seolah tidak ada (404), bukan 403, agar keberadaannya tidak bocor (IDOR).
 * Kontak/alamat/dokumen pengajuan tidak pernah dikembalikan di sini.
 */
class PortalAdopterService
{
    public function __construct(private readonly PemantauanPascaAdopsiService $pemantauan)
    {
    }

    /** @return Collection<int, SerahTerima> */
    public function adopsiSaya(User $user): Collection
    {
        return $this->kueri($user)->orderByDesc('tanggal_serah_terima')->orderByDesc('id_serah_terima')->get();
    }

    /** Satu adopsi milik akun ini; 404 (ModelNotFoundException) bila bukan miliknya. */
    public function milik(User $user, int $idSerahTerima): SerahTerima
    {
        return $this->kueri($user)->whereKey($idSerahTerima)->firstOrFail();
    }

    /** @return array<string, mixed> ringkasan untuk daftar */
    public function ringkas(SerahTerima $st): array
    {
        $kucing = $st->pengajuanAdopsi->kucing;
        $p = $this->pemantauan->untuk($st->id_serah_terima) ?? [];

        return [
            'id_serah_terima'      => $st->id_serah_terima,
            'nomor_pengajuan'      => $st->pengajuanAdopsi->nomor_pengajuan,
            'tanggal_serah_terima' => $st->tanggal_serah_terima->format('Y-m-d'),
            'kucing'               => [
                'id_kucing'   => $kucing->id_kucing,
                'kode_kucing' => $kucing->kode_kucing,
                'nama_kucing' => $kucing->nama_kucing,
                'foto_utama'  => $kucing->fotoUtama ? asset($kucing->fotoUtama->url_foto) : null,
            ],
            'pemantauan'           => [
                'status'                 => $p['status_pemantauan'] ?? null,   // aktif | terlambat | selesai | berakhir
                'kondisi_terkini'        => $p['kondisi_terkini'] ?? null,
                'jatuh_tempo'            => $p['jatuh_tempo'] ?? null,
                'terlambat_hari'         => $p['terlambat_hari'] ?? 0,
                'masa_pemantauan_sampai' => $p['masa_pemantauan_sampai'] ?? null,
                'jumlah_update'          => $p['jumlah_update'] ?? 0,
            ],
            'laporan_menunggu'     => LaporanAdopter::where('id_serah_terima', $st->id_serah_terima)->where('status_laporan', LaporanAdopter::MENUNGGU)->count(),
        ];
    }

    /** @return array<string, mixed> ringkasan + riwayat kondisi (resmi) + laporan yang pernah dikirim */
    public function detail(SerahTerima $st): array
    {
        return $this->ringkas($st) + [
            'riwayat_kondisi' => PascaAdopsi::where('id_serah_terima', $st->id_serah_terima)
                ->orderByDesc('tanggal_update')->orderByDesc('id_pasca_adopsi')->get()
                ->map(fn (PascaAdopsi $r) => ['tanggal' => $r->tanggal_update->format('Y-m-d'), 'kondisi' => $r->status_kondisi, 'catatan' => $r->catatan])->all(),
            'laporan_saya'    => LaporanAdopter::where('id_serah_terima', $st->id_serah_terima)
                ->orderByDesc('id_laporan')->limit(20)->get()
                ->map(fn (LaporanAdopter $l) => [
                    'id_laporan'     => $l->id_laporan,
                    'tanggal'        => $l->tanggal_laporan->format('Y-m-d'),
                    'kondisi'        => $l->status_kondisi,
                    'catatan'        => $l->catatan,
                    'status'         => $l->status_laporan,
                    // Alasan hanya relevan bila ditolak; untuk yang dicatat tidak ada catatan tambahan yang perlu dilihat adopter.
                    'alasan_ditolak' => $l->status_laporan === LaporanAdopter::DITOLAK ? $l->catatan_admin : null,
                ])->all(),
        ];
    }

    private function kueri(User $user): Builder
    {
        $adopter = Adopter::where('id_user', $user->id_user)->value('id_adopter');

        return SerahTerima::query()
            ->with(['pengajuanAdopsi.kucing.fotoUtama'])
            ->whereHas('pengajuanAdopsi', fn (Builder $q) => $q->where('id_adopter', $adopter ?? 0));
    }
}

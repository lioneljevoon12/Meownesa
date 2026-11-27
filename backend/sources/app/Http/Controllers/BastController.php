<?php

namespace App\Http\Controllers;

use App\Models\SerahTerima;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Lembar Berita Acara Serah Terima (BAST) yang bisa dicetak (dev-backend-17). Route: role:admin.
 *
 * Dikembalikan sebagai HTML siap cetak (ctrl+P, "Simpan sebagai PDF") tanpa dependensi pembuat PDF. Memuat data pribadi
 * adopter, jadi tidak pernah di-cache dan setiap pembukaan dicatat di audit log (seperti pengunduhan berkas serah terima).
 */
class BastController extends Controller
{
    public function tampilkan(Request $request, int $id): Response
    {
        $st = SerahTerima::with(['pengajuanAdopsi.kucing.lokasi.kampus'])->findOrFail($id);
        $p = $st->pengajuanAdopsi;

        AuditLogger::record($request->user(), 'CETAK_BAST', 'serah_terima', $st->id_serah_terima);

        $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $t = Carbon::parse($st->tanggal_serah_terima->format('Y-m-d'));

        $html = view('bast', [
            'serahTerima' => $st,
            'pengajuan'   => $p,
            'kucing'      => $p->kucing,
            'nomor'       => $p->nomor_pengajuan,
            'tanggal'     => $hari[$t->dayOfWeek].', '.$t->day.' '.$bulan[$t->month].' '.$t->year,
            'lokasi'      => trim(($p->kucing->lokasi?->nama_lokasi ?? '-').' ('.($p->kucing->lokasi?->kampus?->nama_kampus ?? '-').')'),
            'petugas'     => $request->user()->nama,
        ])->render();

        return response($html, 200, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag'  => 'noindex, nofollow',
        ]);
    }
}

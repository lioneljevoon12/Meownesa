<?php

namespace App\Services;

use App\Models\LampiranPengajuan;
use App\Models\PengajuanAdopsi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan lampiran pengajuan adopsi (KTP, foto rumah, dokumen pendukung).
 *
 * Berkas disimpan di disk privat `lampiran` (bukan di public/) dengan nama acak buatan server;
 * nama berkas dari pengguna tidak pernah dipakai. Berkas disimpan apa adanya (tidak di-encode ulang)
 * agar KTP tetap terbaca jelas dan metadata foto rumah tetap dapat diperiksa admin.
 */
class LampiranService
{
    public const DISK = 'lampiran';
    public const MAKS_PER_PENGAJUAN = 5;
    public const MAKS_KB = 5120;

    private const EKSTENSI = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    /** Aturan validasi untuk satu berkas lampiran. @return array<int, string> */
    public static function aturanBerkas(): array
    {
        return [
            'file',
            'mimetypes:'.implode(',', array_keys(self::EKSTENSI)),
            'mimes:jpg,jpeg,png,webp,pdf',
            'max:'.self::MAKS_KB,
        ];
    }

    public function simpan(PengajuanAdopsi $pengajuan, string $jenis, UploadedFile $berkas): LampiranPengajuan
    {
        $ekstensi = self::EKSTENSI[$berkas->getMimeType()] ?? 'bin';
        $path = $pengajuan->id_pengajuan.'/'.Str::lower(Str::random(40)).'.'.$ekstensi;

        Storage::disk(self::DISK)->put($path, file_get_contents($berkas->getRealPath()));

        return LampiranPengajuan::create([
            'id_pengajuan'   => $pengajuan->id_pengajuan,
            'jenis_lampiran' => $jenis,
            'url_file'       => $path,   // path relatif terhadap disk `lampiran`, BUKAN URL publik
        ]);
    }

    public function hapusBerkas(string $path): void
    {
        if (! str_contains($path, '..')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

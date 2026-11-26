<?php

namespace App\Services;

use App\Models\SerahTerima;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan berkas serah terima: dokumen perjanjian/BAST (PDF atau gambar hasil pindai) dan foto
 * penyerahan. Keduanya memuat data pribadi adopter, jadi disimpan di disk privat `serah_terima`
 * dengan nama acak; hanya admin yang dapat mengunduh (setiap akses diaudit).
 */
class SerahTerimaBerkasService
{
    public const DISK = 'serah_terima';
    public const MAKS_KB = 5120;

    /** kolom -> jenis MIME yang diizinkan */
    public const JENIS = [
        'dokumen_perjanjian' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'foto_serah_terima'  => ['image/jpeg', 'image/png', 'image/webp'],
    ];

    private const EKSTENSI = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    /** Aturan validasi satu berkas; jenis diperiksa dari isi berkas, bukan ekstensi. @return array<int, string> */
    public static function aturan(string $kolom): array
    {
        return [
            'file',
            'mimetypes:'.implode(',', self::JENIS[$kolom]),
            'mimes:'.($kolom === 'dokumen_perjanjian' ? 'jpg,jpeg,png,webp,pdf' : 'jpg,jpeg,png,webp'),
            'max:'.self::MAKS_KB,
        ];
    }

    /** Simpan berkas, ganti yang lama bila ada, dan kembalikan path barunya (kolom belum disimpan ke model). */
    public function simpan(SerahTerima $st, string $kolom, UploadedFile $berkas): string
    {
        $ekstensi = self::EKSTENSI[$berkas->getMimeType()] ?? 'bin';
        $path = $st->id_serah_terima.'/'.$kolom.'-'.Str::lower(Str::random(40)).'.'.$ekstensi;

        Storage::disk(self::DISK)->put($path, file_get_contents($berkas->getRealPath()));

        return $path;
    }

    public function hapus(?string $path): void
    {
        if ($path && ! str_contains($path, '..')) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}

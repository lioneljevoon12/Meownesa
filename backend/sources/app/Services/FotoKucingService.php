<?php

namespace App\Services;

use App\Models\Kucing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan foto kucing. Pengolahan gambarnya (JPEG maks. 1280 px, metadata EXIF/GPS dibuang,
 * MEOW-NF-06) dilakukan {@see PengolahGambar}.
 */
class FotoKucingService
{
    public const DISK = 'kucing_foto';
    public const FOLDER = 'upload';
    public const URL_PREFIX = 'images/kucing/'.self::FOLDER.'/';
    public const MAKS_SISI = 1280;

    public function __construct(private readonly PengolahGambar $gambar)
    {
    }

    /**
     * Simpan unggahan, kembalikan path relatif terhadap folder public (disimpan di foto_kucing.url_foto).
     *
     * @throws \RuntimeException bila berkas bukan gambar yang dapat diproses
     */
    public function simpan(Kucing $kucing, UploadedFile $berkas): string
    {
        $jpeg = $this->gambar->keJpeg($berkas, self::MAKS_SISI);

        $nama = strtolower($kucing->kode_kucing).'-'.Str::lower(Str::random(10)).'.jpg';
        Storage::disk(self::DISK)->put(self::FOLDER.'/'.$nama, $jpeg);

        return self::URL_PREFIX.$nama;
    }

    /**
     * Hapus berkas fisik. Hanya berkas unggahan (folder upload/) yang dihapus;
     * foto bawaan seeder dibiarkan karena ikut terlacak di repositori.
     */
    public function hapusBerkas(string $urlFoto): void
    {
        if (str_starts_with($urlFoto, self::URL_PREFIX) && ! str_contains($urlFoto, '..')) {
            Storage::disk(self::DISK)->delete(self::FOLDER.'/'.basename($urlFoto));
        }
    }
}

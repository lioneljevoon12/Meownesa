<?php

namespace App\Services;

use App\Models\Kucing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pemrosesan dan penyimpanan foto kucing.
 *
 * Setiap unggahan di-encode ulang menjadi JPEG (maks. 1280 px sisi terpanjang): ukuran berkas turun,
 * dan seluruh metadata EXIF — termasuk koordinat GPS tempat foto diambil — ikut terbuang
 * sehingga lokasi pribadi tidak bocor ke halaman publik (MEOW-NF-06).
 */
class FotoKucingService
{
    public const DISK = 'kucing_foto';
    public const FOLDER = 'upload';
    public const URL_PREFIX = 'images/kucing/'.self::FOLDER.'/';
    public const MAKS_SISI = 1280;
    public const KUALITAS_JPEG = 85;

    /** Simpan unggahan, kembalikan path relatif terhadap folder public (disimpan di foto_kucing.url_foto). */
    public function simpan(Kucing $kucing, UploadedFile $berkas): string
    {
        $this->naikkanBatasMemori();

        $bytes = file_get_contents($berkas->getRealPath());
        $asal = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($asal === false) {
            throw new RuntimeException('Berkas gambar tidak dapat diproses.');
        }

        if ($berkas->getMimeType() === 'image/jpeg') {
            $asal = $this->putarSesuaiExif($asal, $berkas->getRealPath());
        }

        $lebar = imagesx($asal);
        $tinggi = imagesy($asal);
        $skala = min(1, self::MAKS_SISI / max($lebar, $tinggi));
        $baruLebar = max(1, (int) round($lebar * $skala));
        $baruTinggi = max(1, (int) round($tinggi * $skala));

        // Kanvas putih: PNG/WebP transparan diratakan karena JPEG tidak punya alpha.
        $kanvas = imagecreatetruecolor($baruLebar, $baruTinggi);
        imagefill($kanvas, 0, 0, imagecolorallocate($kanvas, 255, 255, 255));
        imagecopyresampled($kanvas, $asal, 0, 0, 0, 0, $baruLebar, $baruTinggi, $lebar, $tinggi);

        ob_start();
        imagejpeg($kanvas, null, self::KUALITAS_JPEG);
        $jpeg = (string) ob_get_clean();
        imagedestroy($asal);
        imagedestroy($kanvas);

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

    /** @param  \GdImage  $gambar */
    private function putarSesuaiExif($gambar, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $gambar;
        }

        $exif = @exif_read_data($path);
        $sudut = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $sudut === 0 ? $gambar : (imagerotate($gambar, $sudut, 0) ?: $gambar);
    }

    /** Foto resolusi tinggi (mis. 48 MP) membutuhkan banyak memori saat didekode GD. */
    private function naikkanBatasMemori(): void
    {
        $batas = ini_get('memory_limit');
        if ($batas !== '-1' && $this->keByte($batas) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
    }

    private function keByte(string $nilai): int
    {
        $angka = (int) $nilai;

        return match (strtoupper(substr(trim($nilai), -1))) {
            'G' => $angka * 1024 ** 3,
            'M' => $angka * 1024 ** 2,
            'K' => $angka * 1024,
            default => $angka,
        };
    }
}

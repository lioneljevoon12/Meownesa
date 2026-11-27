<?php

namespace App\Services;

use App\Models\Artikel;
use App\Models\PemberianMakan;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Aturan inti CMS artikel (dev-backend-12): slug, pembersihan isi, siklus status publikasi, dan thumbnail.
 */
class ArtikelService
{
    public const DISK = 'artikel_thumbnail';
    public const FOLDER = 'upload';
    public const URL_PREFIX = 'images/artikel/'.self::FOLDER.'/';
    public const MAKS_SISI = 1280;

    public function __construct(private readonly PengolahGambar $gambar)
    {
    }

    /** Slug unik dari judul ("judul-artikel", "judul-artikel-2", ...). Nilai yang bentrok dengan rute statis diberi akhiran. */
    public function buatSlug(string $judul, ?int $abaikanId = null): string
    {
        $dasar = Str::limit(Str::slug($judul), 200, '');
        $dasar = trim($dasar, '-') ?: 'artikel';
        if (in_array($dasar, Artikel::SLUG_TERCADANG, true)) {
            $dasar .= '-artikel';
        }

        $slug = $dasar;
        for ($i = 2; $this->slugDipakai($slug, $abaikanId); $i++) {
            $slug = $dasar.'-'.$i;
        }

        return $slug;
    }

    /**
     * Isi artikel adalah teks polos/Markdown: seluruh tag HTML dibuang agar tidak ada skrip yang bisa
     * tersimpan lalu tampil di halaman publik (XSS), apa pun cara frontend merendernya.
     */
    public function bersihkanIsi(string $isi): string
    {
        // Isi blok <script>/<style> ikut dibuang (strip_tags saja menyisakan teks di dalamnya).
        $isi = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $isi) ?? $isi;

        return trim(str_replace(["\r\n", "\r"], "\n", strip_tags($isi)));
    }

    /**
     * Terapkan status publikasi: tanggal terbit tercatat saat PERTAMA kali terbit (terbit ulang tidak
     * mengubahnya), dan artikel yang tidak lagi terbit otomatis dilepas dari sematan/unggulan.
     */
    public function terapkanStatus(Artikel $artikel, string $status): void
    {
        $artikel->status_publikasi = $status;

        if ($status === Artikel::STATUS_PUBLISHED) {
            $artikel->tanggal_diterbitkan ??= $this->hariIni();
        } else {
            $artikel->is_featured = false;
            $artikel->is_pinned = false;
        }
    }

    public function hariIni(): string
    {
        return Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString();
    }

    /** Simpan thumbnail (JPEG hasil olahan), kembalikan path relatif terhadap folder public. */
    public function simpanThumbnail(UploadedFile $berkas): string
    {
        $jpeg = $this->gambar->keJpeg($berkas, self::MAKS_SISI);

        $nama = 'thumb-'.Str::lower(Str::random(12)).'.jpg';
        Storage::disk(self::DISK)->put(self::FOLDER.'/'.$nama, $jpeg);

        return self::URL_PREFIX.$nama;
    }

    /** Hanya berkas unggahan (folder upload/) yang dihapus; gambar bawaan seeder dibiarkan. */
    public function hapusThumbnail(?string $url): void
    {
        if ($url && str_starts_with($url, self::URL_PREFIX) && ! str_contains($url, '..')) {
            Storage::disk(self::DISK)->delete(self::FOLDER.'/'.basename($url));
        }
    }

    private function slugDipakai(string $slug, ?int $abaikanId): bool
    {
        return Artikel::where('slug', $slug)
            ->when($abaikanId, fn ($q) => $q->where('id_artikel', '!=', $abaikanId))
            ->exists();
    }
}

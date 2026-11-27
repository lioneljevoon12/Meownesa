<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi thumbnail artikel: hanya JPG/PNG/WebP (dicek dari isi berkas), maks. 5 MB dan 6000x6000 px.
 * Hasil akhir selalu dikompres ulang (EXIF/GPS dibuang) oleh ArtikelService.
 */
class UploadThumbnailArtikelRequest extends FormRequest
{
    public const MAKS_KB = 5120;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<int, mixed> aturan untuk satu berkas thumbnail */
    public static function aturanBerkas(bool $wajib): array
    {
        return [
            $wajib ? 'required' : 'nullable', 'file',
            'mimetypes:image/jpeg,image/png,image/webp',
            'mimes:jpg,jpeg,png,webp',
            'max:'.self::MAKS_KB,
            'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000',
        ];
    }

    /** @return array<string, string> */
    public static function pesanBerkas(): array
    {
        return [
            'thumbnail.required'   => 'Berkas thumbnail wajib diunggah (field: thumbnail).',
            'thumbnail.file'       => 'Thumbnail harus berupa berkas.',
            'thumbnail.uploaded'   => 'Thumbnail gagal diunggah. Pastikan ukurannya tidak lebih dari 5 MB.',
            'thumbnail.mimetypes'  => 'Format thumbnail harus JPG, PNG, atau WebP.',
            'thumbnail.mimes'      => 'Format thumbnail harus JPG, PNG, atau WebP.',
            'thumbnail.max'        => 'Ukuran thumbnail maksimal 5 MB.',
            'thumbnail.dimensions' => 'Dimensi thumbnail minimal 200x200 px dan maksimal 6000x6000 px.',
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['thumbnail' => self::aturanBerkas(true)];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::pesanBerkas();
    }
}

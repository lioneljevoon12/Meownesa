<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi unggah foto kucing: hanya JPG/PNG/WebP (dicek dari isi berkas, bukan hanya ekstensi),
 * maks. 5 MB dan 6000x6000 px. Hasil akhir selalu dikompres ulang oleh FotoKucingService.
 */
class UploadFotoKucingRequest extends FormRequest
{
    public const MAKS_KB = 5120;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'foto' => [
                'required', 'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:'.self::MAKS_KB,
                'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'foto.required'   => 'Berkas foto wajib diunggah (field: foto).',
            'foto.file'       => 'Foto harus berupa berkas.',
            'foto.uploaded'   => 'Foto gagal diunggah. Pastikan ukurannya tidak lebih dari 5 MB.',
            'foto.mimetypes'  => 'Format foto harus JPG, PNG, atau WebP.',
            'foto.mimes'      => 'Format foto harus JPG, PNG, atau WebP.',
            'foto.max'        => 'Ukuran foto maksimal 5 MB.',
            'foto.dimensions' => 'Dimensi foto minimal 200x200 px dan maksimal 6000x6000 px.',
        ];
    }
}

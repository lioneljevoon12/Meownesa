<?php

namespace App\Http\Requests;

use App\Models\SerahTerima;
use App\Services\SerahTerimaBerkasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Unggah atau ganti berkas serah terima belakangan (mis. BAST yang baru selesai dipindai). */
class UploadBerkasSerahTerimaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        SerahTerima::findOrFail($this->route('id'));   // tidak ada -> 404 sebelum validasi isian
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $jenis = array_keys(SerahTerimaBerkasService::JENIS);
        $kolom = in_array($this->input('jenis'), $jenis, true) ? $this->input('jenis') : 'foto_serah_terima';

        return [
            'jenis'  => ['required', 'string', Rule::in($jenis)],
            'berkas' => ['required', ...SerahTerimaBerkasService::aturan($kolom)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'jenis.required'   => 'Jenis berkas wajib diisi.',
            'jenis.in'         => 'Jenis berkas harus dokumen_perjanjian atau foto_serah_terima.',
            'berkas.required'  => 'Berkas wajib diunggah (field: berkas).',
            'berkas.file'      => 'Berkas harus berupa file.',
            'berkas.mimetypes' => 'Format berkas tidak diizinkan untuk jenis ini (dokumen: PDF/JPG/PNG/WebP; foto: JPG/PNG/WebP).',
            'berkas.mimes'     => 'Format berkas tidak diizinkan untuk jenis ini (dokumen: PDF/JPG/PNG/WebP; foto: JPG/PNG/WebP).',
            'berkas.max'       => 'Ukuran berkas maksimal 5 MB.',
            'berkas.uploaded'  => 'Berkas gagal diunggah. Pastikan ukurannya tidak lebih dari 5 MB.',
        ];
    }
}

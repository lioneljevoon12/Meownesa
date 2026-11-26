<?php

namespace App\Http\Requests;

use App\Models\LampiranPengajuan;
use App\Services\LampiranService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah lampiran ke pengajuan yang sudah ada; identitas pengaju dibuktikan dengan kode tracking. */
class UploadLampiranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kode_tracking'  => ['required', 'string', 'max:50'],
            'jenis_lampiran' => ['required', 'string', Rule::in(LampiranPengajuan::JENIS)],
            'berkas'         => ['required', ...LampiranService::aturanBerkas()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['kode_tracking' => 'kode tracking', 'jenis_lampiran' => 'jenis lampiran'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'            => ':Attribute wajib diisi.',
            'string'              => ':Attribute harus berupa teks.',
            'max'                 => ':Attribute maksimal :max karakter.',
            'jenis_lampiran.in'   => 'Jenis lampiran harus salah satu dari: '.implode(', ', LampiranPengajuan::JENIS).'.',
            'berkas.required'     => 'Berkas wajib diunggah (field: berkas).',
            'berkas.file'         => 'Berkas harus berupa file.',
            'berkas.mimetypes'    => 'Format berkas harus JPG, PNG, WebP, atau PDF.',
            'berkas.mimes'        => 'Format berkas harus JPG, PNG, WebP, atau PDF.',
            'berkas.max'          => 'Ukuran berkas maksimal 5 MB.',
            'berkas.uploaded'     => 'Berkas gagal diunggah. Pastikan ukurannya tidak lebih dari 5 MB.',
        ];
    }
}

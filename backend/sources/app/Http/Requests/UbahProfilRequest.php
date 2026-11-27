<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Ubah profil sendiri. Hanya nama: email dan role tidak bisa diubah sendiri (email ganti lewat Super Admin). */
class UbahProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nama'))) {
            $this->merge(['nama' => trim(preg_replace('/\s+/', ' ', $this->input('nama')) ?? '')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['nama' => ['required', 'string', 'min:2', 'max:100']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'string'   => ':Attribute harus berupa teks.',
            'min'      => ':Attribute minimal :min karakter.',
            'max'      => ':Attribute maksimal :max karakter.',
        ];
    }
}

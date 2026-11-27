<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Super Admin mendaftarkan Admin Jaga Satwa baru. Password tidak dikirim: pemilik akun mengaturnya sendiri lewat email. */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rapi = [];
        if (is_string($this->input('nama'))) {
            $rapi['nama'] = trim(preg_replace('/\s+/', ' ', $this->input('nama')) ?? '');
        }
        if (is_string($this->input('email'))) {
            $rapi['email'] = mb_strtolower(trim($this->input('email')));
        }
        $this->merge($rapi);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama'  => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'string'   => ':Attribute harus berupa teks.',
            'email'    => 'Format email tidak valid.',
            'min'      => ':Attribute minimal :min karakter.',
            'max'      => ':Attribute maksimal :max karakter.',
            'unique'   => 'Email sudah terdaftar.',
        ];
    }
}

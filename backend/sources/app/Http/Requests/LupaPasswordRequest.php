<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Minta tautan reset password (publik). Hanya bentuk email yang divalidasi: tidak pernah memeriksa apakah email terdaftar. */
class LupaPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:150']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'string'   => ':Attribute harus berupa teks.',
            'email'    => 'Format email tidak valid.',
            'max'      => ':Attribute maksimal :max karakter.',
        ];
    }
}

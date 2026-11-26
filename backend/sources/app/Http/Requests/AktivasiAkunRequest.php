<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Aktivasi akun adopter lewat tautan: atur password pertama kali. */
class AktivasiAkunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email', 'max:150'],
            'token'    => ['required', 'string', 'max:200'],
            // bcrypt hanya memakai 72 byte pertama; batas 64 karakter mencegah password panjang terpotong diam-diam.
            'password' => ['required', 'string', 'confirmed', 'max:64', 'different:email', Password::min(8)->letters()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'            => ':Attribute wajib diisi.',
            'string'              => ':Attribute harus berupa teks.',
            'email'               => 'Format email tidak valid.',
            'max'                 => ':Attribute maksimal :max karakter.',
            'password.confirmed'  => 'Konfirmasi password tidak sama.',
            'password.different'  => 'Password tidak boleh sama dengan email.',
            'password.min'        => 'Password minimal 8 karakter.',
            'password.letters'    => 'Password harus mengandung huruf.',
            'password.numbers'    => 'Password harus mengandung angka.',
        ];
    }
}

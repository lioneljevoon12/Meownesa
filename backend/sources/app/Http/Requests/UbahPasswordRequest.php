<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Ubah password sendiri: wajib menyebut password lama (token curian saja tidak cukup untuk mengambil alih akun). */
class UbahPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'password_lama' => ['required', 'string', 'max:255'],
            // bcrypt hanya memakai 72 byte pertama; batas 64 karakter mencegah password panjang terpotong diam-diam.
            'password'      => ['required', 'string', 'confirmed', 'max:64', 'different:password_lama', 'different:email_akun', Password::min(8)->letters()->numbers()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email_akun' => (string) $this->user()?->email]);   // hanya untuk aturan `different`
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'             => ':Attribute wajib diisi.',
            'string'               => ':Attribute harus berupa teks.',
            'max'                  => ':Attribute maksimal :max karakter.',
            'password.confirmed'   => 'Konfirmasi password tidak sama.',
            'password.different'   => 'Password baru tidak boleh sama dengan password lama atau email akun.',
            'password.min'         => 'Password minimal 8 karakter.',
            'password.letters'     => 'Password harus mengandung huruf.',
            'password.numbers'     => 'Password harus mengandung angka.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['password_lama' => 'password lama', 'password' => 'password baru'];
    }
}

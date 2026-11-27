<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Atur password baru lewat tautan reset (publik). */
class ResetPasswordRequest extends FormRequest
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
            'password' => ['required', 'string', 'confirmed', 'max:64', 'different:email', Password::min(8)->letters()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return (new AktivasiAkunRequest)->messages();
    }
}

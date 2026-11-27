<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Aktifkan atau tangguhkan akun. (`menunggu_aktivasi` hanya bisa diakhiri pemilik akun lewat tautan aktivasi.) */
class UbahStatusUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['aktif', 'nonaktif'])]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'in'       => ':Attribute harus aktif atau nonaktif.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['status' => 'status akun'];
    }
}

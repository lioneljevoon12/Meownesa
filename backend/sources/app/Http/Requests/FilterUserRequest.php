<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string daftar akun (Super Admin). */
class FilterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role'     => ['nullable', Rule::in(User::ROLES)],
            'status'   => ['nullable', Rule::in(User::STATUS_AKUN)],
            'q'        => ['nullable', 'string', 'max:100'],
            'sort'     => ['nullable', Rule::in(['terbaru', 'nama'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'in'      => 'Nilai :attribute tidak valid.',
            'integer' => ':Attribute harus berupa angka.',
            'between' => ':Attribute harus di antara :min dan :max.',
            'max'     => ':Attribute maksimal :max karakter.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['per_page' => 'jumlah per halaman', 'status' => 'status akun'];
    }
}

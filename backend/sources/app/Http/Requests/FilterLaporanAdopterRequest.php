<?php

namespace App\Http\Requests;

use App\Models\LaporanAdopter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string kotak masuk laporan adopter (admin). */
class FilterLaporanAdopterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'   => ['nullable', Rule::in(LaporanAdopter::STATUS)],
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
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['per_page' => 'jumlah per halaman', 'status' => 'status laporan'];
    }
}

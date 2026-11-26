<?php

namespace App\Http\Requests;

use App\Models\PascaAdopsi;
use App\Services\PemantauanPascaAdopsiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string daftar pemantauan pasca-adopsi. */
class FilterPascaAdopsiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'   => ['nullable', Rule::in(PemantauanPascaAdopsiService::STATUS_PEMANTAUAN)],
            'kondisi'  => ['nullable', Rule::in(PascaAdopsi::STATUS)],
            'q'        => ['nullable', 'string', 'max:100'],
            'sort'     => ['nullable', Rule::in(['prioritas', 'terbaru', 'terlama'])],
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
        return ['per_page' => 'jumlah per halaman', 'status' => 'status pemantauan', 'kondisi' => 'kondisi'];
    }
}

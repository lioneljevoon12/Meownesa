<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string penampil audit log. Tanggal `dari`/`sampai` (Y-m-d) dibaca menurut WIB dan inklusif. */
class FilterAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama_tabel' => ['nullable', 'string', 'max:50'],
            'aktivitas'  => ['nullable', 'string', 'max:100'],
            'id_user'    => ['nullable', 'integer', 'min:1'],
            'id_data'    => ['nullable', 'integer', 'min:1'],
            'dari'       => ['nullable', 'date_format:Y-m-d'],
            'sampai'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'sort'       => ['nullable', Rule::in(['terbaru', 'terlama'])],
            'per_page'   => ['nullable', 'integer', 'between:1,100'],
            'page'       => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'in'                  => 'Nilai :attribute tidak valid.',
            'integer'             => ':Attribute harus berupa angka.',
            'between'             => ':Attribute harus di antara :min dan :max.',
            'max'                 => ':Attribute maksimal :max karakter.',
            'date_format'         => 'Format :attribute harus YYYY-MM-DD.',
            'after_or_equal'      => 'Tanggal sampai tidak boleh sebelum tanggal dari.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['nama_tabel' => 'nama tabel', 'id_user' => 'id pengguna', 'id_data' => 'id data', 'per_page' => 'jumlah per halaman', 'dari' => 'tanggal dari', 'sampai' => 'tanggal sampai'];
    }
}

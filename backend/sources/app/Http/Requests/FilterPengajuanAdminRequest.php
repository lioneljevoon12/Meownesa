<?php

namespace App\Http\Requests;

use App\Models\PengajuanAdopsi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string antrean pengajuan adopsi untuk admin: status, kucing, pencarian, tanggal, urutan, paginasi. */
class FilterPengajuanAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'    => ['nullable', Rule::in(PengajuanAdopsi::STATUS)],
            'id_kucing' => ['nullable', 'integer', Rule::exists('kucing', 'id_kucing')],
            'q'         => ['nullable', 'string', 'max:100'],
            'dari'      => ['nullable', 'date_format:Y-m-d'],
            'sampai'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'sort'      => ['nullable', Rule::in(['terlama', 'terbaru'])],
            'per_page'  => ['nullable', 'integer', 'between:1,100'],
            'page'      => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'in'                    => 'Nilai :attribute tidak valid.',
            'date_format'           => ':Attribute harus berformat YYYY-MM-DD.',
            'integer'               => ':Attribute harus berupa angka.',
            'between'               => ':Attribute harus di antara :min dan :max.',
            'max'                   => ':Attribute maksimal :max karakter.',
            'sampai.after_or_equal' => 'Tanggal "sampai" tidak boleh sebelum "dari".',
            'id_kucing.exists'      => 'Kucing tidak ditemukan.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_kucing' => 'kucing', 'per_page' => 'jumlah per halaman'];
    }
}

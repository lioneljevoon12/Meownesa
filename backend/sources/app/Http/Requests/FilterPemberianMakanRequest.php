<?php

namespace App\Http\Requests;

use App\Models\PemberianMakan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string daftar feeding log: filter tanggal/lokasi/kucing/status dan paginasi. */
class FilterPemberianMakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tanggal'   => ['nullable', 'date_format:Y-m-d'],
            'dari'      => ['nullable', 'date_format:Y-m-d'],
            'sampai'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'id_kampus' => ['nullable', 'integer', Rule::exists('kampus', 'id_kampus')],
            'id_lokasi' => ['nullable', 'integer', Rule::exists('lokasi', 'id_lokasi')],
            'id_kucing' => ['nullable', 'integer', Rule::exists('kucing', 'id_kucing')],
            'status'    => ['nullable', Rule::in(PemberianMakan::STATUS)],
            'per_page'  => ['nullable', 'integer', 'between:1,100'],
            'page'      => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date_format'          => ':Attribute harus berformat YYYY-MM-DD.',
            'integer'              => ':Attribute harus berupa angka.',
            'between'              => ':Attribute harus di antara :min dan :max.',
            'in'                   => 'Nilai :attribute tidak valid.',
            'sampai.after_or_equal' => 'Tanggal "sampai" tidak boleh sebelum "dari".',
            'id_kampus.exists'     => 'Kampus tidak ditemukan.',
            'id_lokasi.exists'     => 'Lokasi tidak ditemukan.',
            'id_kucing.exists'     => 'Kucing tidak ditemukan.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_kampus' => 'kampus', 'id_lokasi' => 'lokasi', 'id_kucing' => 'kucing', 'per_page' => 'jumlah per halaman'];
    }
}

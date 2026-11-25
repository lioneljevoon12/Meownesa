<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string rekap feeding: periode (mingguan/bulanan) yang memuat `tanggal`, opsional per kampus/lokasi. */
class RekapPemberianMakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periode'   => ['nullable', Rule::in(['mingguan', 'bulanan'])],
            'tanggal'   => ['nullable', 'date_format:Y-m-d'],
            'id_kampus' => ['nullable', 'integer', Rule::exists('kampus', 'id_kampus')],
            'id_lokasi' => ['nullable', 'integer', Rule::exists('lokasi', 'id_lokasi')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'periode.in'           => 'Periode harus mingguan atau bulanan.',
            'tanggal.date_format'  => 'Tanggal harus berformat YYYY-MM-DD.',
            'integer'              => ':Attribute harus berupa angka.',
            'id_kampus.exists'     => 'Kampus tidak ditemukan.',
            'id_lokasi.exists'     => 'Lokasi tidak ditemukan.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_kampus' => 'kampus', 'id_lokasi' => 'lokasi'];
    }
}

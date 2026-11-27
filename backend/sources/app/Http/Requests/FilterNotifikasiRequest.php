<?php

namespace App\Http\Requests;

use App\Models\Notifikasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string histori notifikasi. */
class FilterNotifikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'       => ['nullable', Rule::in(Notifikasi::STATUS)],
            'kanal'        => ['nullable', Rule::in(Notifikasi::KANAL)],
            'jenis'        => ['nullable', 'string', 'max:50'],
            'id_pengajuan' => ['nullable', 'integer', 'min:1'],
            'per_page'     => ['nullable', 'integer', 'between:1,100'],
            'page'         => ['nullable', 'integer', 'min:1'],
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
        return ['per_page' => 'jumlah per halaman', 'status' => 'status pengiriman', 'id_pengajuan' => 'id pengajuan'];
    }
}

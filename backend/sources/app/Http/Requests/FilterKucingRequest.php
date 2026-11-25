<?php

namespace App\Http\Requests;

use App\Models\Kucing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string katalog publik: filter, pencarian, urutan, dan paginasi. */
class FilterKucingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Status yang boleh dipakai sebagai filter. Publik hanya status publik. */
    protected function statusBoleh(): array
    {
        return Kucing::STATUS_PUBLIK;
    }

    public function perHalamanBawaan(): int
    {
        return 12;
    }

    protected function perHalamanMaks(): int
    {
        return 50;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q'             => ['nullable', 'string', 'max:100'],
            'id_kampus'     => ['nullable', 'integer', Rule::exists('kampus', 'id_kampus')],
            'id_lokasi'     => ['nullable', 'integer', Rule::exists('lokasi', 'id_lokasi')],
            'status'        => ['nullable', Rule::in($this->statusBoleh())],
            'jenis_kelamin' => ['nullable', Rule::in(['Jantan', 'Betina'])],
            'kelompok_usia' => ['nullable', Rule::in(Kucing::KELOMPOK_USIA)],
            'status_steril' => ['nullable', Rule::in(Kucing::STATUS_STERIL)],
            'sort'          => ['nullable', Rule::in(['nama', 'terbaru'])],
            'per_page'      => ['nullable', 'integer', 'between:1,'.$this->perHalamanMaks()],
            'page'          => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'integer'          => ':Attribute harus berupa angka.',
            'max'              => ':Attribute maksimal :max karakter.',
            'between'          => ':Attribute harus di antara :min dan :max.',
            'in'               => 'Nilai :attribute tidak valid.',
            'id_kampus.exists' => 'Kampus tidak ditemukan.',
            'id_lokasi.exists' => 'Lokasi tidak ditemukan.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'id_kampus'     => 'kampus',
            'id_lokasi'     => 'lokasi',
            'jenis_kelamin' => 'jenis kelamin',
            'kelompok_usia' => 'kelompok usia',
            'status_steril' => 'status steril',
            'per_page'      => 'jumlah per halaman',
        ];
    }
}

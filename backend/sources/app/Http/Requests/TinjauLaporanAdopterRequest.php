<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Meninjau laporan adopter. Mencatat: `catatan_admin` opsional (keterangan internal). Menolak: `alasan` wajib
 * (ditampilkan ke adopter). Rute tolak memakai kunci `alasan`; rute catat memakai `catatan_admin`.
 */
class TinjauLaporanAdopterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['alasan', 'catatan_admin'] as $kunci) {
            if (is_string($this->input($kunci))) {
                $this->merge([$kunci => trim($this->input($kunci))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->is('*/tolak')
            ? ['alasan' => ['required', 'string', 'min:5', 'max:500']]
            : ['catatan_admin' => ['nullable', 'string', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'string'   => ':Attribute harus berupa teks.',
            'min'      => ':Attribute minimal :min karakter.',
            'max'      => ':Attribute maksimal :max karakter.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['catatan_admin' => 'catatan admin'];
    }
}

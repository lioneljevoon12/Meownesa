<?php

namespace App\Http\Requests;

use App\Models\Artikel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string daftar artikel (publik dan admin; `status`, `featured`, `pinned` hanya dipakai admin). */
class FilterArtikelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rapi = [];
        foreach (['featured', 'pinned'] as $kunci) {
            if (is_string($this->query($kunci))) {
                $nilai = filter_var($this->query($kunci), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($nilai !== null) {
                    $rapi[$kunci] = $nilai;
                }
            }
        }
        $this->merge($rapi);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $admin = $this->is('api/admin/*');

        return [
            'kategori' => ['nullable', Rule::in(Artikel::KATEGORI)],
            'q'        => ['nullable', 'string', 'max:100'],
            'status'   => $admin ? ['nullable', Rule::in(Artikel::STATUS)] : ['prohibited'],
            'featured' => $admin ? ['nullable', 'boolean'] : ['prohibited'],
            'pinned'   => $admin ? ['nullable', 'boolean'] : ['prohibited'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'in'         => 'Nilai :attribute tidak valid.',
            'integer'    => ':Attribute harus berupa angka.',
            'between'    => ':Attribute harus di antara :min dan :max.',
            'max'        => ':Attribute maksimal :max karakter.',
            'boolean'    => ':Attribute harus berupa true atau false.',
            'prohibited' => 'Filter :attribute tidak tersedia.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['per_page' => 'jumlah per halaman', 'featured' => 'unggulan', 'pinned' => 'sematan'];
    }
}

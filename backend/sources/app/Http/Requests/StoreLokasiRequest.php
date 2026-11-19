<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi tambah lokasi (MEOW-F-05, MEOW-NF-13).
 * Otorisasi role sudah ditangani middleware `role:admin` pada route.
 */
class StoreLokasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_kampus'   => ['required', 'integer', Rule::exists('kampus', 'id_kampus')],
            'nama_lokasi' => ['required', 'string', 'max:100', $this->uniqueNamaPerKampus($this->input('id_kampus'))],
            'koordinat_x' => ['required', 'numeric', 'between:-90,90', 'decimal:0,8'],
            'koordinat_y' => ['required', 'numeric', 'between:-180,180', 'decimal:0,8'],
        ];
    }

    protected function uniqueNamaPerKampus(mixed $idKampus, ?int $abaikanId = null): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('lokasi', 'nama_lokasi')->where('id_kampus', (int) $idKampus);

        return $abaikanId ? $rule->ignore($abaikanId, 'id_lokasi') : $rule;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nama_lokasi'))) {
            $this->merge(['nama_lokasi' => preg_replace('/\s+/', ' ', trim($this->input('nama_lokasi')))]);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'id_kampus'   => 'kampus',
            'nama_lokasi' => 'nama lokasi',
            'koordinat_x' => 'koordinat X (lintang)',
            'koordinat_y' => 'koordinat Y (bujur)',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'           => ':Attribute wajib diisi.',
            'integer'            => ':Attribute harus berupa angka bulat.',
            'numeric'            => ':Attribute harus berupa angka.',
            'string'             => ':Attribute harus berupa teks.',
            'max'                => ':Attribute maksimal :max karakter.',
            'between'            => ':Attribute harus di antara :min dan :max.',
            'decimal'            => ':Attribute maksimal 8 angka di belakang koma.',
            'id_kampus.exists'   => 'Kampus tidak ditemukan. Lokasi wajib terikat pada kampus resmi UNESA.',
            'nama_lokasi.unique' => 'Nama lokasi sudah dipakai di kampus ini.',
        ];
    }
}

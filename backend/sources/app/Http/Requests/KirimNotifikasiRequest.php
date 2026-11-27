<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Kirim email notifikasi: satu (id_notifikasi) atau semua yang PENDING (tanpa id). */
class KirimNotifikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_notifikasi' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['integer' => ':Attribute harus berupa angka.', 'min' => ':Attribute tidak valid.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_notifikasi' => 'id notifikasi'];
    }
}

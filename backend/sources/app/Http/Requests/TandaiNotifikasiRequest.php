<?php

namespace App\Http\Requests;

use App\Models\Notifikasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin melaporkan hasil pengiriman WhatsApp manual. */
class TandaiNotifikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status'  => ['required', Rule::in([Notifikasi::STATUS_TERKIRIM, Notifikasi::STATUS_GAGAL])],
            'catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'in'       => ':Attribute harus TERKIRIM atau GAGAL.',
            'max'      => ':Attribute maksimal :max karakter.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['status' => 'status pengiriman'];
    }
}

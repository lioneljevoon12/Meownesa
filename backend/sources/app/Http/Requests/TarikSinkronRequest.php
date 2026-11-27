<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Query penarikan data offline. `sejak` = kursor dari penarikan sebelumnya (ISO 8601 beserta zona waktu). */
class TarikSinkronRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Klien yang lupa meng-encode "+" di query string mengirim "2026-11-26T10:00:00 07:00": pulihkan tanda + nya.
        if (is_string($this->query('sejak'))) {
            $this->merge(['sejak' => preg_replace('/ (\d{2}:\d{2})$/', '+$1', trim($this->query('sejak')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sejak'        => ['nullable', 'string', 'max:40', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', 'date'],
            'hari_feeding' => ['nullable', 'integer', 'between:1,'.config('sinkron.hari_feeding_maks')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sejak.regex'  => 'Format sejak harus ISO 8601 lengkap dengan zona waktu, contoh 2026-11-26T10:00:00+07:00 atau 2026-11-26T03:00:00Z.',
            'sejak.date'   => 'Nilai sejak bukan waktu yang valid.',
            'integer'      => ':Attribute harus berupa angka.',
            'between'      => ':Attribute harus di antara :min dan :max.',
            'max'          => ':Attribute maksimal :max karakter.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['hari_feeding' => 'jumlah hari feeding'];
    }
}

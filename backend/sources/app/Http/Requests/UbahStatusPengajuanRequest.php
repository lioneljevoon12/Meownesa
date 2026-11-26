<?php

namespace App\Http\Requests;

use App\Models\PengajuanAdopsi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin mengubah status pengajuan. SELESAI tidak termasuk: hanya diset oleh pencatatan serah terima (modul 09). */
class UbahStatusPengajuanRequest extends FormRequest
{
    public const BOLEH = ['SCREENING', 'DISETUJUI', 'DITOLAK'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        PengajuanAdopsi::findOrFail($this->route('id'));   // tidak ada -> 404 sebelum validasi isian

        if (is_string($this->input('status'))) {
            $this->merge(['status' => strtoupper(trim($this->input('status')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(self::BOLEH)]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Status wajib diisi.',
            'status.in'       => 'Status harus salah satu dari: '.implode(', ', self::BOLEH).'. Status SELESAI diatur otomatis saat serah terima dicatat.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\PascaAdopsi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Adopter mengirim kabar kondisi kucingnya. Selain "Sehat", kondisi wajib dijelaskan (sama dengan aturan pemantauan). */
class KirimLaporanAdopterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('catatan'))) {
            $this->merge(['catatan' => trim($this->input('catatan'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status_kondisi' => ['required', 'string', Rule::in(PascaAdopsi::STATUS)],
            'catatan'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->input('status_kondisi') !== 'Sehat' && trim((string) $this->input('catatan')) === '' && ! $v->errors()->has('status_kondisi')) {
                $v->errors()->add('catatan', 'Jelaskan kondisinya di catatan bila kucing tidak dalam keadaan sehat.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'              => ':Attribute wajib diisi.',
            'string'                => ':Attribute harus berupa teks.',
            'max'                   => ':Attribute maksimal :max karakter.',
            'status_kondisi.in'     => 'Kondisi harus salah satu dari: '.implode(', ', PascaAdopsi::STATUS).'.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['status_kondisi' => 'kondisi'];
    }
}

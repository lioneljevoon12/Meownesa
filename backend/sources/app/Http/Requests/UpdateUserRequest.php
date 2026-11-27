<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Ubah nama/email akun admin (bidang yang tidak dikirim tidak berubah). */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rapi = [];
        if (is_string($this->input('nama'))) {
            $rapi['nama'] = trim(preg_replace('/\s+/', ' ', $this->input('nama')) ?? '');
        }
        if (is_string($this->input('email'))) {
            $rapi['email'] = mb_strtolower(trim($this->input('email')));
        }
        $this->merge($rapi);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = is_numeric($this->route('id')) ? (int) $this->route('id') : null;

        return [
            'nama'  => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id, 'id_user')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return (new StoreUserRequest)->messages();
    }
}

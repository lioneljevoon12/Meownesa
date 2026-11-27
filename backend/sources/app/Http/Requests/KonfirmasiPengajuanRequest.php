<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tindakan pelamar yang mengubah data (menarik pengajuan, menghapus lampiran) membutuhkan KODE TRACKING (di path)
 * DAN nomor pengajuan (di sini) sebagai dua faktor: kode tracking yang terlihat/tersebar saja tidak cukup untuk
 * membatalkan pengajuan orang.
 */
class KonfirmasiPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['nomor_pengajuan' => ['required', 'string', 'max:30']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['required' => ':Attribute wajib diisi.', 'string' => ':Attribute harus berupa teks.', 'max' => ':Attribute maksimal :max karakter.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['nomor_pengajuan' => 'nomor pengajuan'];
    }
}

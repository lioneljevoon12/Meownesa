<?php

namespace App\Http\Requests;

use App\Models\CatatanPengajuan;
use App\Models\PemberianMakan;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Catatan internal admin pada pengajuan. Jenis "Kunjungan Rumah" boleh memuat tanggal kunjungan (rencana atau sudah). */
class StoreCatatanPengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('isi'))) {
            $this->merge(['isi' => trim($this->input('isi'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Kunjungan dapat dijadwalkan ke depan; batas atas 1 tahun menahan salah ketik tahun.
        $batas = Carbon::now(PemberianMakan::ZONA_WAKTU)->addYear()->toDateString();

        return [
            'jenis'             => ['nullable', Rule::in(CatatanPengajuan::JENIS)],
            'isi'               => ['required', 'string', 'min:3', 'max:2000'],
            'tanggal_kunjungan' => [
                'nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:'.$batas,
                Rule::prohibitedIf(fn () => $this->input('jenis', 'Catatan') !== 'Kunjungan Rumah'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'                    => ':Attribute wajib diisi.',
            'string'                      => ':Attribute harus berupa teks.',
            'min'                         => ':Attribute minimal :min karakter.',
            'max'                         => ':Attribute maksimal :max karakter.',
            'jenis.in'                    => 'Jenis harus salah satu dari: '.implode(', ', CatatanPengajuan::JENIS).'.',
            'tanggal_kunjungan.date_format' => 'Tanggal kunjungan harus berformat YYYY-MM-DD.',
            'tanggal_kunjungan.prohibited'  => 'Tanggal kunjungan hanya untuk catatan berjenis Kunjungan Rumah.',
            'tanggal_kunjungan.after_or_equal'  => 'Tanggal kunjungan tidak masuk akal (sebelum tahun 2020).',
            'tanggal_kunjungan.before_or_equal' => 'Tanggal kunjungan terlalu jauh di masa depan (maksimal 1 tahun).',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['isi' => 'isi catatan', 'tanggal_kunjungan' => 'tanggal kunjungan'];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\PemberianMakan;
use App\Models\PengajuanAdopsi;
use App\Models\ScreeningAdopsi;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Hasil pemeriksaan calon adopter oleh Admin (MEOW-F-17, F-18). Empat kriteria baku Jaga Satwa:
 * kelayakan hunian, kestabilan ekonomi, pemahaman perawatan, dan sikap terhadap dokter hewan.
 */
class StoreScreeningRequest extends FormRequest
{
    public const KRITERIA = ['kelayakan_hunian', 'kestabilan_ekonomi', 'pemahaman_perawatan', 'sikap_proklinik'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Pengajuan tidak ada -> 404 sebelum validasi isian.
        PengajuanAdopsi::findOrFail($this->route('pengajuan'));

        $rapi = [];
        foreach (self::KRITERIA as $kolom) {
            if (is_string($this->input($kolom))) {
                $rapi[$kolom] = trim($this->input($kolom));
            }
        }
        $this->merge($rapi);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hariIni = Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString();

        return [
            'kelayakan_hunian'    => ['nullable', 'string', 'max:2000'],
            'kestabilan_ekonomi'  => ['nullable', 'string', 'max:2000'],
            'pemahaman_perawatan' => ['nullable', 'string', 'max:2000'],
            'sikap_proklinik'     => ['nullable', 'string', 'max:2000'],
            'hasil_screening'     => ['required', 'string', Rule::in(ScreeningAdopsi::HASIL)],
            'tanggal_screening'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:'.$hariIni],
        ];
    }

    /** Pemohon tidak boleh dinyatakan Lolos tanpa keempat kriteria diperiksa dan dicatat. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->input('hasil_screening') !== 'Lolos') {
                return;
            }
            foreach (self::KRITERIA as $kolom) {
                if (trim((string) $this->input($kolom)) === '') {
                    $v->errors()->add($kolom, 'Hasil "Lolos" mengharuskan keempat kriteria pemeriksaan diisi. Kriteria ini masih kosong.');
                }
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'kelayakan_hunian'    => 'kelayakan hunian',
            'kestabilan_ekonomi'  => 'kestabilan ekonomi',
            'pemahaman_perawatan' => 'pemahaman perawatan',
            'sikap_proklinik'     => 'sikap terhadap dokter hewan',
            'hasil_screening'     => 'hasil pemeriksaan',
            'tanggal_screening'   => 'tanggal pemeriksaan',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'                      => ':Attribute wajib diisi.',
            'string'                        => ':Attribute harus berupa teks.',
            'max'                           => ':Attribute maksimal :max karakter.',
            'hasil_screening.in'            => 'Hasil pemeriksaan harus salah satu dari: '.implode(', ', ScreeningAdopsi::HASIL).'.',
            'tanggal_screening.date_format' => 'Tanggal pemeriksaan harus berformat YYYY-MM-DD.',
            'tanggal_screening.after_or_equal'  => 'Tanggal pemeriksaan tidak masuk akal (sebelum tahun 2020).',
            'tanggal_screening.before_or_equal' => 'Tanggal pemeriksaan tidak boleh di masa depan.',
        ];
    }
}

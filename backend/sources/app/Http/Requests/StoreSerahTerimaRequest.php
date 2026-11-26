<?php

namespace App\Http\Requests;

use App\Models\PemberianMakan;
use App\Models\PengajuanAdopsi;
use App\Services\SerahTerimaBerkasService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/** Catat serah terima kucing kepada adopter yang pengajuannya sudah disetujui (MEOW-F-21 s/d F-24). */
class StoreSerahTerimaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        PengajuanAdopsi::findOrFail($this->route('pengajuan'));   // tidak ada -> 404 sebelum validasi isian
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hariIni = Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString();

        return [
            'tanggal_serah_terima' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:'.$hariIni],
            'dokumen_perjanjian'   => ['nullable', ...SerahTerimaBerkasService::aturan('dokumen_perjanjian')],
            'foto_serah_terima'    => ['nullable', ...SerahTerimaBerkasService::aturan('foto_serah_terima')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tanggal_serah_terima' => 'tanggal serah terima',
            'dokumen_perjanjian'   => 'dokumen perjanjian',
            'foto_serah_terima'    => 'foto serah terima',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'                           => ':Attribute wajib diisi.',
            'file'                               => ':Attribute harus berupa berkas.',
            'tanggal_serah_terima.date_format'   => 'Tanggal serah terima harus berformat YYYY-MM-DD.',
            'tanggal_serah_terima.after_or_equal'  => 'Tanggal serah terima tidak masuk akal (sebelum tahun 2020).',
            'tanggal_serah_terima.before_or_equal' => 'Tanggal serah terima tidak boleh di masa depan.',
            'dokumen_perjanjian.mimetypes'       => 'Format dokumen perjanjian harus PDF, JPG, PNG, atau WebP.',
            'dokumen_perjanjian.mimes'           => 'Format dokumen perjanjian harus PDF, JPG, PNG, atau WebP.',
            'foto_serah_terima.mimetypes'        => 'Format foto serah terima harus JPG, PNG, atau WebP.',
            'foto_serah_terima.mimes'            => 'Format foto serah terima harus JPG, PNG, atau WebP.',
            'max'                                => 'Ukuran :attribute maksimal 5 MB.',
            'uploaded'                           => ':Attribute gagal diunggah. Pastikan ukurannya tidak lebih dari 5 MB.',
        ];
    }
}

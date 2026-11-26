<?php

namespace App\Http\Requests;

use App\Models\PascaAdopsi;
use App\Models\PemberianMakan;
use App\Models\SerahTerima;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Dasar validasi catatan kondisi pasca-adopsi (MEOW-F-26). Adopter memberi kabar lewat WhatsApp/CP;
 * admin yang mencatatnya di sini (MEOW-BR-13).
 */
abstract class PascaAdopsiRequest extends FormRequest
{
    /** Serah terima pemilik catatan. */
    abstract protected function idSerahTerima(): ?int;

    /** Catatan yang sedang diubah; null saat menambah. */
    abstract protected function catatanSaatIni(): ?PascaAdopsi;

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
    protected function aturanDasar(bool $sebagian): array
    {
        $wajib = $sebagian ? ['sometimes', 'required'] : ['required'];
        $hariIni = Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString();

        return [
            'tanggal_update' => [...$wajib, 'date_format:Y-m-d', 'before_or_equal:'.$hariIni],
            'status_kondisi' => [...$wajib, 'string', \Illuminate\Validation\Rule::in(PascaAdopsi::STATUS)],
            'catatan'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * - Tanggal pembaruan tidak boleh sebelum tanggal serah terima.
     * - Kondisi selain "Sehat" wajib dijelaskan di catatan (apa yang terjadi, tindakan yang diambil).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $saatIni = $this->catatanSaatIni();
            $st = SerahTerima::find($this->idSerahTerima());
            $tanggal = $this->input('tanggal_update', $saatIni?->tanggal_update?->format('Y-m-d'));
            $status = $this->input('status_kondisi', $saatIni?->status_kondisi);
            $catatan = $this->has('catatan') ? $this->input('catatan') : $saatIni?->catatan;

            if ($st && $tanggal && $tanggal < $st->tanggal_serah_terima->format('Y-m-d')) {
                $v->errors()->add('tanggal_update', 'Tanggal pembaruan tidak boleh sebelum tanggal serah terima ('.$st->tanggal_serah_terima->format('Y-m-d').').');
            }

            if ($status && $status !== 'Sehat' && trim((string) $catatan) === '') {
                $v->errors()->add('catatan', "Catatan wajib diisi untuk kondisi {$status} (jelaskan kondisi dan tindakan yang diambil).");
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_serah_terima' => 'serah terima', 'tanggal_update' => 'tanggal pembaruan', 'status_kondisi' => 'kondisi kucing'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'                      => ':Attribute wajib diisi.',
            'integer'                       => ':Attribute harus berupa angka bulat.',
            'string'                        => ':Attribute harus berupa teks.',
            'max'                           => ':Attribute maksimal :max karakter.',
            'id_serah_terima.exists'        => 'Data serah terima tidak ditemukan.',
            'tanggal_update.date_format'    => 'Tanggal pembaruan harus berformat YYYY-MM-DD.',
            'tanggal_update.before_or_equal' => 'Tanggal pembaruan tidak boleh di masa depan.',
            'status_kondisi.in'             => 'Kondisi harus salah satu dari: '.implode(', ', PascaAdopsi::STATUS).'.',
        ];
    }
}

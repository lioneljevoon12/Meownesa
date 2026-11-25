<?php

namespace App\Http\Requests;

use App\Models\Kucing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Dasar validasi tambah/ubah data kucing (MEOW-F-03, F-04, MEOW-BR-01, MEOW-BR-14, MEOW-NF-13).
 * Otorisasi role sudah ditangani middleware `role:admin` pada route.
 */
abstract class KucingRequest extends FormRequest
{
    /** Data kucing yang sedang diubah; null saat menambah. */
    abstract protected function kucingSaatIni(): ?Kucing;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rapi = [];
        foreach (['nama_kucing', 'deskripsi'] as $kolom) {
            if (is_string($this->input($kolom))) {
                $rapi[$kolom] = trim(preg_replace('/[ \t]+/', ' ', $this->input($kolom)));
            }
        }
        if (is_string($this->input('kode_kucing'))) {
            $rapi['kode_kucing'] = strtoupper(trim($this->input('kode_kucing')));
        }
        $this->merge($rapi);
    }

    /**
     * MEOW-BR-14: kucing hanya boleh berstatus "Tersedia" bila steril, dinyatakan sehat, dan siap
     * diadopsi oleh Admin. Pernyataan "sehat" berupa konfirmasi eksplisit `konfirmasi_sehat`
     * setiap kali status dinaikkan ke Tersedia. Konsistensi (MEOW-NF-14): kucing Tersedia
     * tidak boleh diubah menjadi belum steril tanpa menurunkan statusnya dulu.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $saatIni = $this->kucingSaatIni();
            $status = $this->input('status_kucing', $saatIni?->status_kucing ?? 'Terdata');
            $steril = $this->input('status_steril', $saatIni?->status_steril ?? 'Tidak Diketahui');

            if ($status !== 'Tersedia') {
                return;
            }

            if ($steril !== 'Sudah') {
                $v->errors()->add(
                    $this->has('status_kucing') ? 'status_kucing' : 'status_steril',
                    'Kucing hanya dapat berstatus Tersedia bila sudah steril (status steril saat ini: '.$steril.').'
                );
            }

            $naikKeTersedia = ($saatIni?->status_kucing) !== 'Tersedia';
            if ($naikKeTersedia && ! $this->boolean('konfirmasi_sehat')) {
                $v->errors()->add('konfirmasi_sehat', 'Konfirmasi bahwa kucing dalam kondisi sehat dan siap diadopsi wajib dicentang (konfirmasi_sehat = true).');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'id_lokasi'        => 'lokasi',
            'kode_kucing'      => 'kode kucing',
            'nama_kucing'      => 'nama kucing',
            'jenis_kelamin'    => 'jenis kelamin',
            'kelompok_usia'    => 'kelompok usia',
            'status_steril'    => 'status steril',
            'status_kucing'    => 'status kucing',
            'konfirmasi_sehat' => 'konfirmasi sehat',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'                => ':Attribute wajib diisi.',
            'string'                  => ':Attribute harus berupa teks.',
            'integer'                 => ':Attribute harus berupa angka bulat.',
            'boolean'                 => ':Attribute harus bernilai true atau false.',
            'max'                     => ':Attribute maksimal :max karakter.',
            'in'                      => 'Nilai :attribute tidak valid.',
            'id_lokasi.exists'        => 'Lokasi tidak ditemukan.',
            'kode_kucing.regex'       => 'Kode kucing hanya boleh berisi huruf, angka, dan tanda hubung.',
            'kode_kucing.unique'      => 'Kode kucing sudah dipakai kucing lain.',
            'status_kucing.in'        => 'Status hanya dapat diisi manual dengan: '.implode(', ', Kucing::STATUS_MANUAL).'. Status Proses Screening dan Diadopsi diatur otomatis oleh proses adopsi.',
        ];
    }
}

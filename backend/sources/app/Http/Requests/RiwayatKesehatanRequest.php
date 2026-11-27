<?php

namespace App\Http\Requests;

use App\Models\RiwayatKesehatan;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dasar validasi catatan kesehatan dan perawatan (MEOW-F-10 s/d F-12, MEOW-NF-13).
 * Klinik/dokter hewan tidak punya akun (MEOW-BR-09): seluruh catatan diinput Admin Jaga Satwa.
 */
abstract class RiwayatKesehatanRequest extends FormRequest
{
    /** Id kucing pemilik catatan (dari route atau dari catatan yang diubah). */
    abstract protected function idKucing(): int;

    /** Catatan yang sedang diubah; null saat menambah. */
    abstract protected function catatanSaatIni(): ?RiwayatKesehatan;

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
        return self::aturanIsian($sebagian) + ['client_uuid' => ['nullable', 'uuid']];
    }

    /**
     * Aturan isian catatan (tanpa id kucing). Statis agar juga dipakai pencatatan batch dari HP.
     *
     * @return array<string, mixed>
     */
    public static function aturanIsian(bool $sebagian): array
    {
        $wajib = $sebagian ? ['sometimes', 'required'] : ['required'];
        // Tanggal tindakan tidak boleh di masa depan (menurut WIB); riwayat = yang sudah terjadi.
        $hariIni = Carbon::now(RiwayatKesehatan::ZONA_WAKTU)->toDateString();

        return [
            'jenis_tindakan' => [...$wajib, 'string', Rule::in(RiwayatKesehatan::JENIS)],
            'tanggal'        => [...$wajib, 'date_format:Y-m-d', 'after_or_equal:2015-01-01', 'before_or_equal:'.$hariIni],
            'catatan'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * - "Lainnya" wajib dijelaskan di catatan.
     * - Satu kucing hanya punya satu catatan Sterilisasi (sterilisasi tidak terjadi dua kali;
     *   duplikat hampir pasti salah input).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Kirim ulang catatan yang sama (UUID sudah tersimpan) idempoten: jangan ditolak sebagai duplikat sterilisasi.
            $uuid = $this->input('client_uuid');
            if ($uuid && RiwayatKesehatan::where('client_uuid', $uuid)->exists()) {
                return;
            }

            $saatIni = $this->catatanSaatIni();
            $jenis = $this->input('jenis_tindakan', $saatIni?->jenis_tindakan);
            $catatan = $this->has('catatan') ? $this->input('catatan') : $saatIni?->catatan;

            if ($jenis === 'Lainnya' && trim((string) $catatan) === '') {
                $v->errors()->add('catatan', 'Catatan wajib diisi untuk jenis tindakan "Lainnya".');
            }

            if ($jenis === RiwayatKesehatan::JENIS_STERILISASI) {
                $sudahAda = RiwayatKesehatan::query()
                    ->where('id_kucing', $this->idKucing())
                    ->where('jenis_tindakan', RiwayatKesehatan::JENIS_STERILISASI)
                    ->when($saatIni, fn ($q) => $q->whereKeyNot($saatIni->id_kesehatan))
                    ->exists();

                if ($sudahAda) {
                    $v->errors()->add('jenis_tindakan', 'Kucing ini sudah punya catatan sterilisasi. Ubah catatan yang ada, bukan menambah baru.');
                }
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['jenis_tindakan' => 'jenis tindakan'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'                    => ':Attribute wajib diisi.',
            'string'                      => ':Attribute harus berupa teks.',
            'max'                         => ':Attribute maksimal :max karakter.',
            'jenis_tindakan.in'           => 'Jenis tindakan harus salah satu dari: '.implode(', ', RiwayatKesehatan::JENIS).'.',
            'tanggal.date_format'         => 'Tanggal harus berformat YYYY-MM-DD.',
            'tanggal.after_or_equal'      => 'Tanggal tindakan tidak masuk akal (sebelum tahun 2015).',
            'tanggal.before_or_equal'     => 'Tanggal tindakan tidak boleh di masa depan.',
            'uuid'                        => 'client_uuid harus berupa UUID yang valid.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Services\LampiranService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulir pengajuan adopsi PUBLIK tanpa akun (MEOW-F-14, MEOW-BR-03). Hanya data yang memang
 * punya kolom di tabel pengajuan_adopsi; penilaian kelayakan dilakukan admin di tahap screening.
 */
class AjukanAdopsiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rapi = [];

        if (is_string($this->input('nama_pemohon'))) {
            $rapi['nama_pemohon'] = trim(preg_replace('/\s+/', ' ', $this->input('nama_pemohon')));
        }
        if (is_string($this->input('email'))) {
            $rapi['email'] = mb_strtolower(trim($this->input('email')));
        }
        if (is_string($this->input('alamat'))) {
            $rapi['alamat'] = trim($this->input('alamat'));
        }
        if (is_string($this->input('nomor_whatsapp'))) {
            $rapi['nomor_whatsapp'] = self::normalisasiWhatsapp($this->input('nomor_whatsapp'));
        }

        $this->merge($rapi);
    }

    /** 0812-3456-7890 / +62 812 3456 7890 / 6281234567890  ->  6281234567890 */
    public static function normalisasiWhatsapp(string $nomor): string
    {
        $angka = preg_replace('/[^0-9]/', '', $nomor);

        return match (true) {
            str_starts_with($angka, '62') => $angka,
            str_starts_with($angka, '0')  => '62'.substr($angka, 1),
            str_starts_with($angka, '8')  => '62'.$angka,
            default                       => $angka,
        };
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_kucing'         => ['required', 'integer', Rule::exists('kucing', 'id_kucing')],
            'nama_pemohon'      => ['required', 'string', 'min:3', 'max:100', "regex:/^[\\pL][\\pL\\s.'-]*$/u"],
            'nomor_whatsapp'    => ['required', 'string', 'regex:/^628[1-9][0-9]{7,11}$/'],
            'email'             => ['required', 'string', 'email', 'max:150'],
            'alamat'            => ['required', 'string', 'min:10', 'max:500'],
            'persetujuan_data'  => ['required', 'accepted'],
            'ktp'               => ['nullable', ...LampiranService::aturanBerkas()],
            'foto_rumah'        => ['nullable', ...LampiranService::aturanBerkas()],
            'dokumen_pendukung' => ['nullable', ...LampiranService::aturanBerkas()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'id_kucing'         => 'kucing',
            'nama_pemohon'      => 'nama',
            'nomor_whatsapp'    => 'nomor WhatsApp',
            'alamat'            => 'alamat',
            'persetujuan_data'  => 'persetujuan pengolahan data',
            'ktp'               => 'berkas KTP',
            'foto_rumah'        => 'foto rumah',
            'dokumen_pendukung' => 'dokumen pendukung',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'                 => ':Attribute wajib diisi.',
            'string'                   => ':Attribute harus berupa teks.',
            'integer'                  => ':Attribute harus berupa angka bulat.',
            'min'                      => ':Attribute minimal :min karakter.',
            'max'                      => ':Attribute maksimal :max karakter.',
            'email'                    => 'Format email tidak valid.',
            'file'                     => ':Attribute harus berupa berkas.',
            'id_kucing.exists'         => 'Kucing tidak ditemukan.',
            'nama_pemohon.regex'       => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
            'nomor_whatsapp.regex'     => 'Nomor WhatsApp tidak valid. Contoh: 081234567890 atau +6281234567890.',
            'persetujuan_data.accepted' => 'Anda harus menyetujui pengolahan data pribadi untuk keperluan proses adopsi.',
            'mimetypes'                => 'Format :attribute harus JPG, PNG, WebP, atau PDF.',
            'mimes'                    => 'Format :attribute harus JPG, PNG, WebP, atau PDF.',
            'ktp.max'                  => 'Ukuran :attribute maksimal 5 MB.',
            'foto_rumah.max'           => 'Ukuran :attribute maksimal 5 MB.',
            'dokumen_pendukung.max'    => 'Ukuran :attribute maksimal 5 MB.',
            'uploaded'                 => ':Attribute gagal diunggah. Pastikan ukurannya tidak lebih dari 5 MB.',
        ];
    }
}

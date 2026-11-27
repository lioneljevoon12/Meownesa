<?php

namespace App\Http\Requests;

use App\Models\Artikel;
use App\Services\ArtikelService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Aturan bersama membuat/mengubah artikel (CMS). Judul dan isi dibersihkan sebelum divalidasi. */
abstract class ArtikelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** id artikel yang sedang diubah (null saat membuat), dipakai untuk pengecekan slug unik. */
    abstract protected function idArtikel(): ?int;

    /** Status akhir artikel setelah permintaan ini diterapkan. */
    abstract protected function statusAkhir(): string;

    /** @return array<int, string> status yang boleh dipilih lewat permintaan ini */
    abstract protected function statusDiizinkan(): array;

    protected function prepareForValidation(): void
    {
        $rapi = [];

        foreach (['judul', 'slug'] as $kunci) {
            if (is_string($this->input($kunci))) {
                $rapi[$kunci] = trim($this->input($kunci));
            }
        }
        if (is_string($this->input('isi'))) {
            $rapi['isi'] = app(ArtikelService::class)->bersihkanIsi($this->input('isi'));
        }
        // Multipart mengirim boolean sebagai teks ("true"/"false"/"1"/"0").
        foreach (['is_featured', 'is_pinned'] as $kunci) {
            if (is_string($this->input($kunci))) {
                $nilai = filter_var($this->input($kunci), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($nilai !== null) {
                    $rapi[$kunci] = $nilai;
                }
            }
        }

        $this->merge($rapi);
    }

    /** @return array<string, mixed> */
    protected function aturanDasar(bool $sebagian): array
    {
        $wajib = $sebagian ? 'sometimes' : 'required';

        return [
            'judul'            => [$wajib, 'string', 'min:5', 'max:200'],
            'kategori'         => [$wajib, Rule::in(Artikel::KATEGORI)],
            'isi'              => [$wajib, 'string', 'min:20', 'max:50000'],
            'slug'             => [
                'sometimes', 'nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Artikel::SLUG_TERCADANG),
                Rule::unique('artikel', 'slug')->ignore($this->idArtikel(), 'id_artikel'),
            ],
            'status_publikasi' => ['sometimes', Rule::in($this->statusDiizinkan())],
            'is_featured'      => ['sometimes', 'boolean'],
            'is_pinned'        => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // Hanya artikel yang terbit yang bisa tampil di landing page.
            if ($this->statusAkhir() !== Artikel::STATUS_PUBLISHED) {
                foreach (['is_featured' => 'unggulan', 'is_pinned' => 'sematan'] as $kunci => $label) {
                    if ($this->boolean($kunci)) {
                        $v->errors()->add($kunci, "Artikel harus berstatus PUBLISHED agar dapat dijadikan {$label}.");
                    }
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'   => ':Attribute wajib diisi.',
            'string'     => ':Attribute harus berupa teks.',
            'min'        => ':Attribute minimal :min karakter.',
            'max'        => ':Attribute maksimal :max karakter.',
            'in'         => 'Nilai :attribute tidak valid.',
            'boolean'    => ':Attribute harus berupa true atau false.',
            'unique'     => ':Attribute sudah dipakai artikel lain.',
            'not_in'     => ':Attribute tidak boleh memakai kata tercadang ini.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (contoh: panduan-adopsi-kucing).',
        ] + UploadThumbnailArtikelRequest::pesanBerkas();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['status_publikasi' => 'status publikasi', 'is_featured' => 'unggulan', 'is_pinned' => 'sematan'];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\PascaAdopsi;

/** Koreksi catatan kondisi (perubahan sebagian). Serah terima pemilik catatan tidak dapat dipindahkan. */
class UpdatePascaAdopsiRequest extends PascaAdopsiRequest
{
    private ?PascaAdopsi $cache = null;

    protected function catatanSaatIni(): ?PascaAdopsi
    {
        return $this->cache ??= PascaAdopsi::find($this->route('id'));
    }

    protected function idSerahTerima(): ?int
    {
        return $this->catatanSaatIni()?->id_serah_terima;
    }

    protected function prepareForValidation(): void
    {
        PascaAdopsi::findOrFail($this->route('id'));   // tidak ada -> 404 sebelum validasi isian

        parent::prepareForValidation();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->aturanDasar(sebagian: true);
    }
}

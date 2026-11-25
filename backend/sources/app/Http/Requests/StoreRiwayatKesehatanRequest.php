<?php

namespace App\Http\Requests;

use App\Models\Kucing;
use App\Models\RiwayatKesehatan;

class StoreRiwayatKesehatanRequest extends RiwayatKesehatanRequest
{
    protected function idKucing(): int
    {
        return (int) $this->route('kucing');
    }

    protected function catatanSaatIni(): ?RiwayatKesehatan
    {
        return null;
    }

    protected function prepareForValidation(): void
    {
        Kucing::findOrFail($this->idKucing());   // kucing tidak ada -> 404 sebelum validasi isian

        parent::prepareForValidation();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->aturanDasar(sebagian: false);
    }
}

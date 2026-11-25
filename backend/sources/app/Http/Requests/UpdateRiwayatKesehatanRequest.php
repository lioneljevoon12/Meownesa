<?php

namespace App\Http\Requests;

use App\Models\RiwayatKesehatan;

/** Ubah catatan kesehatan: semua field opsional. Kucing pemilik catatan tidak dapat dipindahkan. */
class UpdateRiwayatKesehatanRequest extends RiwayatKesehatanRequest
{
    private ?RiwayatKesehatan $cache = null;

    protected function catatanSaatIni(): ?RiwayatKesehatan
    {
        return $this->cache ??= RiwayatKesehatan::find($this->route('id'));
    }

    protected function idKucing(): int
    {
        return (int) $this->catatanSaatIni()?->id_kucing;
    }

    protected function prepareForValidation(): void
    {
        RiwayatKesehatan::findOrFail($this->route('id'));   // tidak ada -> 404 sebelum validasi isian

        parent::prepareForValidation();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->aturanDasar(sebagian: true);
    }
}

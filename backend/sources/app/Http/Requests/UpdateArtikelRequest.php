<?php

namespace App\Http\Requests;

use App\Models\Artikel;

/** Ubah artikel: bidang yang tidak dikirim tidak berubah. Thumbnail diubah lewat endpoint tersendiri. */
class UpdateArtikelRequest extends ArtikelRequest
{
    private ?Artikel $artikel = null;

    private function artikel(): ?Artikel
    {
        $id = $this->route('id');

        return $this->artikel ??= is_numeric($id) ? Artikel::find((int) $id) : null;
    }

    protected function idArtikel(): ?int
    {
        return $this->artikel()?->id_artikel;
    }

    protected function statusAkhir(): string
    {
        return (string) $this->input('status_publikasi', $this->artikel()?->status_publikasi ?? Artikel::STATUS_DRAFT);
    }

    protected function statusDiizinkan(): array
    {
        return Artikel::STATUS;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->aturanDasar(sebagian: true);
    }
}

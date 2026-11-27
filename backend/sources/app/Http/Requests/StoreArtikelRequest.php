<?php

namespace App\Http\Requests;

use App\Models\Artikel;

/** Buat artikel baru (JSON, atau multipart bila langsung menyertakan thumbnail). */
class StoreArtikelRequest extends ArtikelRequest
{
    protected function idArtikel(): ?int
    {
        return null;
    }

    protected function statusAkhir(): string
    {
        return (string) $this->input('status_publikasi', Artikel::STATUS_DRAFT);
    }

    protected function statusDiizinkan(): array
    {
        return [Artikel::STATUS_DRAFT, Artikel::STATUS_PUBLISHED];   // membuat artikel langsung diarsipkan tidak masuk akal
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->aturanDasar(sebagian: false) + ['thumbnail' => UploadThumbnailArtikelRequest::aturanBerkas(false)];
    }
}

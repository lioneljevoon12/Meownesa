<?php

namespace App\Http\Requests;

use App\Models\Kucing;

/** Filter untuk admin: semua status terlihat, halaman lebih besar. */
class FilterKucingAdminRequest extends FilterKucingRequest
{
    protected function statusBoleh(): array
    {
        return Kucing::STATUS;
    }

    public function perHalamanBawaan(): int
    {
        return 20;
    }

    protected function perHalamanMaks(): int
    {
        return 100;
    }
}

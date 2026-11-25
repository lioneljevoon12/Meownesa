<?php

namespace App\Http\Requests;

use App\Models\Kucing;
use Illuminate\Validation\Rule;

class StoreKucingRequest extends KucingRequest
{
    protected function kucingSaatIni(): ?Kucing
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_lokasi'        => ['required', 'integer', Rule::exists('lokasi', 'id_lokasi')],
            'kode_kucing'      => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('kucing', 'kode_kucing')],
            'nama_kucing'      => ['required', 'string', 'max:100'],
            'jenis_kelamin'    => ['required', Rule::in(['Jantan', 'Betina'])],
            'kelompok_usia'    => ['nullable', Rule::in(Kucing::KELOMPOK_USIA)],
            'status_steril'    => ['nullable', Rule::in(Kucing::STATUS_STERIL)],
            'status_kucing'    => ['nullable', Rule::in(Kucing::STATUS_MANUAL)],
            'deskripsi'        => ['nullable', 'string', 'max:2000'],
            'konfirmasi_sehat' => ['nullable', 'boolean'],
        ];
    }
}

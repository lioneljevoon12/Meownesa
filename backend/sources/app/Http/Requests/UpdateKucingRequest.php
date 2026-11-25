<?php

namespace App\Http\Requests;

use App\Models\Kucing;
use Illuminate\Validation\Rule;

/** Ubah data kucing: semua field opsional (perubahan sebagian). */
class UpdateKucingRequest extends KucingRequest
{
    private ?Kucing $cache = null;

    protected function kucingSaatIni(): ?Kucing
    {
        return $this->cache ??= Kucing::find($this->route('id'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $saatIni = $this->kucingSaatIni();

        // Kucing yang sudah berstatus Proses Screening/Diadopsi boleh mengirim ulang status yang sama,
        // tetapi tidak boleh dipindahkan KE status tersebut secara manual.
        $statusBoleh = array_unique([...Kucing::STATUS_MANUAL, $saatIni?->status_kucing ?? 'Terdata']);

        return [
            'id_lokasi'        => ['sometimes', 'required', 'integer', Rule::exists('lokasi', 'id_lokasi')],
            'kode_kucing'      => ['sometimes', 'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('kucing', 'kode_kucing')->ignore($saatIni?->id_kucing, 'id_kucing')],
            'nama_kucing'      => ['sometimes', 'required', 'string', 'max:100'],
            'jenis_kelamin'    => ['sometimes', 'required', Rule::in(['Jantan', 'Betina'])],
            'kelompok_usia'    => ['sometimes', 'nullable', Rule::in(Kucing::KELOMPOK_USIA)],
            'status_steril'    => ['sometimes', 'required', Rule::in(Kucing::STATUS_STERIL)],
            'status_kucing'    => ['sometimes', 'required', Rule::in($statusBoleh)],
            'deskripsi'        => ['sometimes', 'nullable', 'string', 'max:2000'],
            'konfirmasi_sehat' => ['nullable', 'boolean'],
        ];
    }
}

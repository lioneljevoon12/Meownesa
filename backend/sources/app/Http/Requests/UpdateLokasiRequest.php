<?php

namespace App\Http\Requests;

use App\Models\Lokasi;
use Illuminate\Validation\Rule;

/** Ubah lokasi: semua field opsional (perubahan sebagian), aturan sama dengan tambah. */
class UpdateLokasiRequest extends StoreLokasiRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $lokasi = Lokasi::find($this->route('id'));
        $idKampus = $this->input('id_kampus', $lokasi?->id_kampus);

        return [
            'id_kampus'   => ['sometimes', 'required', 'integer', Rule::exists('kampus', 'id_kampus')],
            // nama_lokasi selalu ikut divalidasi saat kampus berubah (lihat prepareForValidation)
            'nama_lokasi' => ['sometimes', 'required', 'string', 'max:100', $this->uniqueNamaPerKampus($idKampus, $lokasi?->id_lokasi)],
            'koordinat_x' => ['sometimes', 'required', 'numeric', 'between:-90,90', 'decimal:0,8'],
            'koordinat_y' => ['sometimes', 'required', 'numeric', 'between:-180,180', 'decimal:0,8'],
        ];
    }

    /**
     * Saat lokasi dipindah ke kampus lain tanpa mengirim nama baru, nama lama tetap harus
     * dicek keunikannya di kampus tujuan.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('id_kampus') && ! $this->has('nama_lokasi')) {
            $nama = Lokasi::whereKey($this->route('id'))->value('nama_lokasi');
            if ($nama !== null) {
                $this->merge(['nama_lokasi' => $nama]);
            }
        }
    }
}

<?php

namespace App\Http\Requests;

use App\Models\PascaAdopsi;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePascaAdopsiRequest extends PascaAdopsiRequest
{
    protected function idSerahTerima(): ?int
    {
        return is_numeric($this->input('id_serah_terima')) ? (int) $this->input('id_serah_terima') : null;
    }

    protected function catatanSaatIni(): ?PascaAdopsi
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['id_serah_terima' => ['required', 'integer', Rule::exists('serah_terima', 'id_serah_terima')]] + $this->aturanDasar(sebagian: false);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // Kucing yang sudah tercatat meninggal tidak menerima pembaruan baru; bila salah catat, koreksi/hapus catatan itu.
            $terakhir = PascaAdopsi::where('id_serah_terima', $this->idSerahTerima())
                ->orderByDesc('tanggal_update')->orderByDesc('id_pasca_adopsi')->value('status_kondisi');

            if (in_array($terakhir, PascaAdopsi::STATUS_AKHIR, true)) {
                $v->errors()->add('id_serah_terima', "Pemantauan sudah berakhir (kondisi terakhir: {$terakhir}). Bila itu salah catat, koreksi atau hapus catatan terakhir.");
            }
        });
    }
}

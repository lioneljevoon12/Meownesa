<?php

namespace App\Http\Requests;

use App\Models\PemberianMakan;
use App\Services\FeedingService;
use Illuminate\Validation\Validator;

/**
 * Ubah catatan pemberian makan (perubahan sebagian). client_uuid tidak dapat diubah.
 * Hanya bagian yang diubah yang diperiksa ulang, sehingga catatan lama (mis. kucingnya kini
 * almarhum) tetap bisa dikoreksi catatannya.
 */
class UpdatePemberianMakanRequest extends StorePemberianMakanRequest
{
    private ?PemberianMakan $cache = null;

    private function catatanSaatIni(): PemberianMakan
    {
        return $this->cache ??= PemberianMakan::findOrFail($this->route('id'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $aturan = app(FeedingService::class)->aturan(sebagian: true);
        unset($aturan['client_uuid']);

        return $aturan;
    }

    protected function prepareForValidation(): void
    {
        $this->catatanSaatIni();   // tidak ada -> 404 sebelum validasi isian

        $data = $this->except('client_uuid');
        if (isset($data['catatan']) && is_string($data['catatan'])) {
            $data['catatan'] = trim($data['catatan']);
        }

        $this->replace($data);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $saatIni = $this->catatanSaatIni();
            $efektif = [
                'id_lokasi'        => $this->input('id_lokasi', $saatIni->id_lokasi),
                'id_kucing'        => $this->has('id_kucing') ? $this->input('id_kucing') : $saatIni->id_kucing,
                'tanggal'          => $this->input('tanggal', $saatIni->tanggal->format('Y-m-d')),
                'status_pemberian' => $this->input('status_pemberian', $saatIni->status_pemberian),
            ];

            $cek = [];
            if ($this->has('id_kucing')) {
                $cek[] = 'kucing';
            }
            if ($this->has('tanggal') || $this->has('status_pemberian')) {
                $cek[] = 'tanggal';
            }
            if ($this->hasAny(['tanggal', 'id_lokasi', 'id_kucing'])) {
                $cek[] = 'duplikat';
            }

            foreach (app(FeedingService::class)->periksa($efektif, $saatIni->id_pemberian, $cek) as $field => $pesan) {
                $v->errors()->add($field, $pesan);
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use App\Services\FeedingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Catat pemberian makan: per titik lokasi (tanpa kucing) atau untuk kucing tertentu. */
class StorePemberianMakanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return app(FeedingService::class)->aturan(sebagian: false);
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        if (isset($data['catatan']) && is_string($data['catatan'])) {
            $data['catatan'] = trim($data['catatan']);
        }

        // Hanya mengisi lokasi dari kucing bila kucing yang dikirim ada; selebihnya biarkan validasi menolak.
        if (! empty($data['id_kucing']) && is_numeric($data['id_kucing'])) {
            $data = app(FeedingService::class)->lengkapi($data);
        }

        $this->replace($data);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $efektif = $this->only(['client_uuid', 'id_lokasi', 'id_kucing', 'tanggal', 'status_pemberian']);
            foreach (app(FeedingService::class)->periksa($efektif) as $field => $pesan) {
                $v->errors()->add($field, $pesan);
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_lokasi' => 'lokasi', 'id_kucing' => 'kucing', 'status_pemberian' => 'status pemberian', 'client_uuid' => 'client_uuid'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required'                 => ':Attribute wajib diisi.',
            'required_without'         => 'Lokasi wajib diisi bila kucing tidak dipilih.',
            'integer'                  => ':Attribute harus berupa angka bulat.',
            'string'                   => ':Attribute harus berupa teks.',
            'max'                      => ':Attribute maksimal :max karakter.',
            'uuid'                     => 'client_uuid harus berupa UUID yang valid.',
            'id_lokasi.exists'         => 'Lokasi tidak ditemukan.',
            'id_kucing.exists'         => 'Kucing tidak ditemukan.',
            'tanggal.date_format'      => 'Tanggal harus berformat YYYY-MM-DD.',
            'tanggal.after_or_equal'   => 'Tanggal tidak masuk akal (sebelum tahun 2020).',
            'status_pemberian.in'      => 'Status pemberian harus salah satu dari: Tuntas, Belum, Terjadwal.',
        ];
    }
}

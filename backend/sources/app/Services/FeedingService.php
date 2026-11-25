<?php

namespace App\Services;

use App\Models\Kucing;
use App\Models\Lokasi;
use App\Models\PemberianMakan;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Aturan dan penyimpanan log pemberian makan (MEOW-F-07 s/d F-09). Dipakai bersama oleh pencatatan
 * tunggal (FormRequest) dan pencatatan massal dari HP (batch), supaya aturannya persis sama.
 *
 * Satu catatan = satu kunci (tanggal, lokasi, kucing). Kucing opsional: kosong berarti catatan
 * untuk seluruh titik lokasi (pola spreadsheet Jaga Satwa). Waktu pemberian tidak dicatat.
 */
class FeedingService
{
    /** @return array<string, mixed> */
    public function aturan(bool $sebagian = false): array
    {
        $wajib = $sebagian ? ['sometimes', 'required'] : ['required'];

        return [
            'client_uuid'      => ['nullable', 'uuid'],
            'id_lokasi'        => $sebagian
                ? ['sometimes', 'required', 'integer', Rule::exists('lokasi', 'id_lokasi')]
                : ['required_without:id_kucing', 'nullable', 'integer', Rule::exists('lokasi', 'id_lokasi')],
            'id_kucing'        => ['nullable', 'integer', Rule::exists('kucing', 'id_kucing')],
            'tanggal'          => [...$wajib, 'date_format:Y-m-d', 'after_or_equal:2020-01-01'],
            'status_pemberian' => [...$wajib, Rule::in(PemberianMakan::STATUS)],
            'catatan'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Bila lokasi tidak dikirim tetapi kucing ada, pakai lokasi tetap kucing tersebut.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function lengkapi(array $data): array
    {
        if (empty($data['id_lokasi']) && ! empty($data['id_kucing'])) {
            $data['id_lokasi'] = Kucing::whereKey($data['id_kucing'])->value('id_lokasi');
        }

        return $data;
    }

    /**
     * Aturan bisnis di luar validasi bentuk. $cek memilih pemeriksaan yang berlaku (saat ubah data,
     * hanya bagian yang diubah yang diperiksa agar catatan historis tetap bisa diedit):
     * 'kucing' (kucing masih di kampus), 'tanggal' (tanggal vs status), 'duplikat'.
     *
     * @param  array<string, mixed>  $efektif  nilai akhir: id_lokasi, id_kucing, tanggal, status_pemberian, client_uuid
     * @param  array<int, string>  $cek
     * @return array<string, string>  pesan galat per field (kosong = lolos)
     */
    public function periksa(array $efektif, ?int $abaikanId = null, array $cek = ['kucing', 'tanggal', 'duplikat']): array
    {
        // Kirim ulang catatan yang sama (UUID sudah tersimpan) bersifat idempoten, bukan galat.
        if (! empty($efektif['client_uuid']) && PemberianMakan::where('client_uuid', $efektif['client_uuid'])->exists()) {
            return [];
        }

        $galat = [];

        if (in_array('kucing', $cek, true) && ! empty($efektif['id_kucing'])) {
            $status = Kucing::whereKey($efektif['id_kucing'])->value('status_kucing');
            if ($status !== null && ! in_array($status, Kucing::STATUS_DI_KAMPUS, true)) {
                $galat['id_kucing'] = "Kucing berstatus {$status} tidak lagi berada di kampus, jadi pemberian makannya tidak dapat dicatat.";
            }
        }

        if (in_array('tanggal', $cek, true)) {
            $hariIni = Carbon::now(PemberianMakan::ZONA_WAKTU)->toDateString();
            if ($efektif['status_pemberian'] === 'Terjadwal') {
                if ($efektif['tanggal'] < $hariIni) {
                    $galat['tanggal'] = 'Pemberian makan berstatus Terjadwal harus bertanggal hari ini atau setelahnya.';
                }
            } elseif ($efektif['tanggal'] > $hariIni) {
                $galat['tanggal'] = 'Tanggal tidak boleh di masa depan untuk status Tuntas atau Belum. Gunakan status Terjadwal untuk rencana.';
            }
        }

        if (! isset($galat['tanggal']) && in_array('duplikat', $cek, true)) {
            $duplikat = $this->cariDuplikat($efektif, $abaikanId);
            if ($duplikat) {
                $galat['tanggal'] = "Sudah ada catatan untuk tanggal, lokasi, dan kucing yang sama (id_pemberian: {$duplikat->id_pemberian}). Ubah catatan yang ada.";
            }
        }

        return $galat;
    }

    /**
     * Simpan satu catatan. Mengembalikan [model, dibuat]; dibuat = false bila UUID yang sama
     * sudah tersimpan (pengiriman ulang). Baris lokasi dikunci agar dua permintaan bersamaan
     * untuk kunci yang sama tidak sama-sama lolos pengecekan duplikat.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: PemberianMakan, 1: bool}
     *
     * @throws ValidationException bila ternyata duplikat saat penguncian
     */
    public function simpan(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                if (! empty($data['client_uuid']) && ($ada = PemberianMakan::where('client_uuid', $data['client_uuid'])->first())) {
                    return [$ada, false];
                }

                Lokasi::whereKey($data['id_lokasi'])->lockForUpdate()->firstOrFail();

                $duplikat = $this->cariDuplikat($data);
                if ($duplikat) {
                    throw ValidationException::withMessages([
                        'tanggal' => "Sudah ada catatan untuk tanggal, lokasi, dan kucing yang sama (id_pemberian: {$duplikat->id_pemberian}). Ubah catatan yang ada.",
                    ]);
                }

                return [PemberianMakan::create($data), true];
            });
        } catch (UniqueConstraintViolationException $e) {
            // Dua pengiriman UUID yang sama bersamaan: yang kalah mengambil catatan pemenang.
            if (! empty($data['client_uuid']) && ($ada = PemberianMakan::where('client_uuid', $data['client_uuid'])->first())) {
                return [$ada, false];
            }
            throw $e;
        }
    }

    /** @param  array<string, mixed>  $e */
    private function cariDuplikat(array $e, ?int $abaikanId = null): ?PemberianMakan
    {
        return PemberianMakan::query()
            ->where('tanggal', $e['tanggal'])
            ->where('id_lokasi', $e['id_lokasi'])
            ->when($e['id_kucing'] ?? null, fn ($q, $v) => $q->where('id_kucing', $v), fn ($q) => $q->whereNull('id_kucing'))
            ->when($abaikanId, fn ($q) => $q->whereKeyNot($abaikanId))
            ->first();
    }
}

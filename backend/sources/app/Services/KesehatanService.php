<?php

namespace App\Services;

use App\Http\Requests\RiwayatKesehatanRequest;
use App\Models\Kucing;
use App\Models\RiwayatKesehatan;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Penyimpanan catatan kesehatan (MEOW-F-10 s/d F-13) yang dipakai bersama oleh pencatatan tunggal dan batch
 * dari HP, supaya aturannya persis sama.
 *
 * Sinkronisasi: tindakan "Sterilisasi" otomatis menetapkan kucing.status_steril = Sudah. Arah sebaliknya
 * TIDAK otomatis: menghapus atau mengubah catatan sterilisasi tidak mengembalikan status ke Belum, karena
 * status "Sudah" juga bisa berasal dari data presensi (tanpa catatan medis).
 *
 * Idempoten bila `client_uuid` dikirim: catatan yang sama yang dikirim ulang (HP offline) tidak membuat
 * data ganda dan tidak ditolak sebagai "sterilisasi sudah ada".
 */
class KesehatanService
{
    /** @return array<string, mixed> aturan satu entri batch */
    public function aturanBatch(): array
    {
        return RiwayatKesehatanRequest::aturanIsian(sebagian: false) + [
            'id_kucing'   => ['required', 'integer', Rule::exists('kucing', 'id_kucing')],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Aturan lintas-bidang yang tidak bisa dinyatakan per field.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string> pesan galat per field (kosong = lolos)
     */
    public function periksa(array $data): array
    {
        $galat = [];

        if (($data['jenis_tindakan'] ?? null) === 'Lainnya' && trim((string) ($data['catatan'] ?? '')) === '') {
            $galat['catatan'] = 'Catatan wajib diisi untuk jenis tindakan "Lainnya".';
        }

        return $galat;
    }

    /**
     * Simpan satu catatan. Mengembalikan [model, dibuat]; dibuat = false bila UUID yang sama sudah tersimpan.
     *
     * @param  array<string, mixed>  $data  jenis_tindakan, tanggal, catatan, client_uuid
     * @return array{0: RiwayatKesehatan, 1: bool}
     *
     * @throws ValidationException
     */
    public function simpan(int $idKucing, array $data, ?User $pelaku): array
    {
        try {
            return DB::transaction(function () use ($idKucing, $data, $pelaku) {
                $kucing = Kucing::whereKey($idKucing)->lockForUpdate()->firstOrFail();

                if (! empty($data['client_uuid']) && ($ada = RiwayatKesehatan::where('client_uuid', $data['client_uuid'])->first())) {
                    $this->pastikanMilikKucing($ada, $kucing);

                    return [$ada, false];
                }

                if (($data['jenis_tindakan'] ?? null) === RiwayatKesehatan::JENIS_STERILISASI) {
                    $this->pastikanSterilisasiTunggal($kucing->id_kucing);
                }

                $catatan = $kucing->riwayatKesehatan()->create($data);
                $this->sinkronSteril($kucing, $catatan, $pelaku);

                return [$catatan, true];
            });
        } catch (UniqueConstraintViolationException $e) {
            // UUID yang sama untuk kucing berbeda terkirim bersamaan: yang kalah mendapat catatan pemenang.
            if (! empty($data['client_uuid']) && ($ada = RiwayatKesehatan::where('client_uuid', $data['client_uuid'])->first())) {
                $this->pastikanMilikKucing($ada, Kucing::findOrFail($idKucing));

                return [$ada, false];
            }
            throw $e;
        }
    }

    /** Pengecekan di dalam transaksi (baris kucing terkunci) agar permintaan bersamaan tidak lolos. */
    public function pastikanSterilisasiTunggal(int $idKucing, ?int $abaikanId = null): void
    {
        $ada = RiwayatKesehatan::where('id_kucing', $idKucing)
            ->where('jenis_tindakan', RiwayatKesehatan::JENIS_STERILISASI)
            ->when($abaikanId, fn ($q) => $q->whereKeyNot($abaikanId))
            ->exists();

        if ($ada) {
            throw ValidationException::withMessages([
                'jenis_tindakan' => 'Kucing ini sudah punya catatan sterilisasi. Ubah catatan yang ada, bukan menambah baru.',
            ]);
        }
    }

    public function sinkronSteril(Kucing $kucing, RiwayatKesehatan $catatan, ?User $pelaku): void
    {
        if ($catatan->jenis_tindakan !== RiwayatKesehatan::JENIS_STERILISASI || $kucing->status_steril === 'Sudah') {
            return;
        }

        $kucing->update(['status_steril' => 'Sudah']);

        // Perubahan data kucing akibat sinkronisasi juga dicatat agar bisa ditelusuri.
        AuditLogger::record($pelaku, 'SINKRON_STERIL', 'kucing', $kucing->id_kucing);
    }

    private function pastikanMilikKucing(RiwayatKesehatan $catatan, Kucing $kucing): void
    {
        if ($catatan->id_kucing !== $kucing->id_kucing) {
            throw ValidationException::withMessages(['client_uuid' => 'client_uuid ini sudah dipakai oleh catatan kucing lain.']);
        }
    }
}

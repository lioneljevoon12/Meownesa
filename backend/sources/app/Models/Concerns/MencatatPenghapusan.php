<?php

namespace App\Models\Concerns;

use App\Models\SyncPenghapusan;

/**
 * Setiap model yang dihapus lewat Eloquent meninggalkan catatan di sync_penghapusan agar HP yang bekerja
 * offline tahu data itu sudah tidak ada. Catatannya memakai nama tabel sebagai `entitas`.
 *
 * Catatan: penghapusan massal lewat query builder (tanpa memuat model) tidak memicu event, jadi
 * hapus data selalu lewat model. Penghapusan berantai oleh basis data (CASCADE) juga tidak tercatat per
 * baris; HP membuang data anak (mis. foto) ketika induknya (kucing) hilang atau berubah.
 */
trait MencatatPenghapusan
{
    public static function bootMencatatPenghapusan(): void
    {
        static::deleted(function ($model) {
            SyncPenghapusan::create([
                'entitas'      => $model->getTable(),
                'id_data'      => $model->getKey(),
                'dihapus_pada' => now(),
            ]);
        });
    }
}

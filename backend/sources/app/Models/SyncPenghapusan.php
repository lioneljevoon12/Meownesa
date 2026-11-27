<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan data yang dihapus (nisan/tombstone) untuk sinkronisasi offline: HP yang menarik perubahan sejak
 * waktu tertentu diberi tahu id mana yang harus dibuang dari salinan lokalnya. Dibersihkan berkala
 * (`sync:bersihkan`); HP yang terlalu lama tidak sinkron harus menarik ulang seluruh data.
 */
class SyncPenghapusan extends Model
{
    public $timestamps = false;

    protected $table = 'sync_penghapusan';

    protected $primaryKey = 'id_penghapusan';

    protected $fillable = ['entitas', 'id_data', 'dihapus_pada'];

    protected $casts = ['dihapus_pada' => 'datetime'];
}

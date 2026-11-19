<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Kampus extends Model
{
    use HasFactory;

    protected $table = 'kampus';
    protected $primaryKey = 'id_kampus';

    protected $fillable = [
        'nama_kampus',
    ];

    public function lokasi(): HasMany
    {
        return $this->hasMany(Lokasi::class, 'id_kampus', 'id_kampus');
    }

    public function kucing(): HasManyThrough
    {
        return $this->hasManyThrough(Kucing::class, Lokasi::class, 'id_kampus', 'id_lokasi', 'id_kampus', 'id_lokasi');
    }
}

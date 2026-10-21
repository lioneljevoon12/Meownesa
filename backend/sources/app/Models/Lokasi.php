<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lokasi extends Model
{
    use HasFactory;

    protected $table = 'lokasi';
    protected $primaryKey = 'id_lokasi';

    protected $fillable = [
        'id_kampus',
        'nama_lokasi',
        'koordinat_x',
        'koordinat_y',
    ];

    protected $casts = [
        'koordinat_x' => 'float',
        'koordinat_y' => 'float',
    ];

    public function kampus(): BelongsTo
    {
        return $this->belongsTo(Kampus::class, 'id_kampus', 'id_kampus');
    }

    public function kucing(): HasMany
    {
        return $this->hasMany(Kucing::class, 'id_lokasi', 'id_lokasi');
    }

    public function pemberianMakan(): HasMany
    {
        return $this->hasMany(PemberianMakan::class, 'id_lokasi', 'id_lokasi');
    }
}

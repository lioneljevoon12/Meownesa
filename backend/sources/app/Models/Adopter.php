<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adopter extends Model
{
    use HasFactory;

    protected $table = 'adopter';
    protected $primaryKey = 'id_adopter';

    protected $fillable = [
        'id_user',
        'tanggal_menjadi_adopter',
        'status_adopter',
    ];

    protected $casts = [
        'tanggal_menjadi_adopter' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function pengajuanAdopsi(): HasMany
    {
        return $this->hasMany(PengajuanAdopsi::class, 'id_adopter', 'id_adopter');
    }
}

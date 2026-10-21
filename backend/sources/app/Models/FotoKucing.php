<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotoKucing extends Model
{
    use HasFactory;

    protected $table = 'foto_kucing';
    protected $primaryKey = 'id_foto_kucing';

    protected $fillable = [
        'id_kucing',
        'url_foto',
        'is_utama',
    ];

    protected $casts = [
        'is_utama' => 'boolean',
    ];

    public function kucing(): BelongsTo
    {
        return $this->belongsTo(Kucing::class, 'id_kucing', 'id_kucing');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Artikel extends Model
{
    use HasFactory;

    protected $table = 'artikel';
    protected $primaryKey = 'id_artikel';

    protected $fillable = [
        'id_user',
        'judul',
        'slug',
        'kategori',
        'isi',
        'thumbnail_url',
        'status_publikasi',
        'is_featured',
        'is_pinned',
        'tanggal_dibuat',
        'tanggal_diterbitkan',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_pinned' => 'boolean',
        'tanggal_dibuat' => 'date',
        'tanggal_diterbitkan' => 'date',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}

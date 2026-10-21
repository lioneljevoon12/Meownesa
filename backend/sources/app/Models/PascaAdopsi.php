<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PascaAdopsi extends Model
{
    use HasFactory;

    protected $table = 'pasca_adopsi';
    protected $primaryKey = 'id_pasca_adopsi';

    protected $fillable = [
        'id_serah_terima',
        'tanggal_update',
        'status_kondisi',
        'catatan',
    ];

    protected $casts = [
        'tanggal_update' => 'date',
    ];

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(SerahTerima::class, 'id_serah_terima', 'id_serah_terima');
    }
}

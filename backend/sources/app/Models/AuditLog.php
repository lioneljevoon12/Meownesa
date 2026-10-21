<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_log';
    protected $primaryKey = 'id_audit';

    protected $fillable = [
        'id_user',
        'aktivitas',
        'nama_tabel',
        'id_data',
        'waktu_aktivitas',
    ];

    protected $casts = [
        'waktu_aktivitas' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}

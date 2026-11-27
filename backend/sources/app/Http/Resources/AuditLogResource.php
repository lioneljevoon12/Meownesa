<?php

namespace App\Http\Resources;

use App\Models\PemberianMakan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AuditLog */
class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id_audit'        => $this->id_audit,
            'waktu_aktivitas' => $this->waktu_aktivitas?->copy()->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String(),
            'aktivitas'       => $this->aktivitas,
            'nama_tabel'      => $this->nama_tabel,
            'id_data'         => $this->id_data,
            // null untuk percobaan login dengan email yang tidak terdaftar.
            'pengguna'        => $this->user ? [
                'id_user'    => $this->user->id_user,
                'nama'       => $this->user->nama,
                'email'      => $this->user->email,
                'role'       => $this->user->role,
                'role_label' => User::ROLE_LABEL[$this->user->role] ?? $this->user->role,
            ] : null,
        ];
    }
}

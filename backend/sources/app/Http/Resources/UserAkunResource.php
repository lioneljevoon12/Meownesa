<?php

namespace App\Http\Resources;

use App\Models\PemberianMakan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Akun untuk Super Admin. Tidak pernah memuat password/hash maupun token aktivasi.
 *
 * @mixin \App\Models\User
 */
class UserAkunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // id_user harus kunci pertama: dipakai middleware audit sebagai id_data.
        return [
            'id_user'        => $this->id_user,
            'nama'           => $this->nama,
            'email'          => $this->email,
            'role'           => $this->role,
            'role_label'     => User::ROLE_LABEL[$this->role] ?? $this->role,
            'status_akun'    => $this->status_akun,
            // Atribut `login_terakhir` disisipkan oleh kueri daftar/detail (subkueri audit_log).
            'login_terakhir' => $this->resource->getAttribute('login_terakhir')
                ? \Carbon\Carbon::parse($this->resource->getAttribute('login_terakhir'))->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String()
                : null,
            'dibuat_pada'    => $this->created_at?->setTimezone(PemberianMakan::ZONA_WAKTU)->toIso8601String(),
        ];
    }
}

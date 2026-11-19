<?php

namespace Tests\Concerns;

use App\Models\User;

/** Helper bersama untuk tes API yang butuh login (dev-backend-02 dst.). */
trait InteractsWithApiAuth
{
    protected const PASSWORD = 'Rahasia-123';

    protected function makeUser(string $role = 'admin', string $status = 'aktif', ?string $email = null): User
    {
        return User::create([
            'nama'          => 'Uji '.$role,
            'email'         => $email ?? $role.'@uji.test',
            'password_hash' => self::PASSWORD,
            'role'          => $role,
            'status_akun'   => $status,
        ]);
    }

    protected function tokenFor(User $user): string
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertOk()->json('data.token');
    }

    /** @return array<string, string> */
    protected function bearer(string $token): array
    {
        // Di produksi tiap request = proses baru. Dalam satu tes, guard menyimpan user dari request
        // sebelumnya, jadi dilupakan di sini agar token yang berbeda benar-benar dievaluasi ulang.
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$token];
    }

    /** @return array<string, string> */
    protected function asRole(string $role): array
    {
        return $this->bearer($this->tokenFor($this->makeUser($role, 'aktif', $role.'.'.uniqid().'@uji.test')));
    }
}

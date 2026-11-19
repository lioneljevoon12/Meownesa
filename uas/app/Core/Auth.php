<?php
declare(strict_types=1);

namespace App\Core;

/** Autentikasi berbasis session + pengecekan role (RBAC). */
final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function hasRole(string ...$roles): bool
    {
        $u = self::user();
        return $u !== null && in_array($u['role'], $roles, true);
    }

    /** Sisa detik penguncian login (0 = tidak terkunci). */
    public static function lockedFor(): int
    {
        $until = $_SESSION['_login_lock'] ?? 0;
        return max(0, $until - time());
    }

    public static function attempt(string $email, string $password): bool
    {
        if (self::lockedFor() > 0) {
            return false;
        }

        $user = Db::one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
        $ok = $user !== null
            && $user['status_akun'] === 'aktif'
            && password_verify($password, $user['password_hash']);

        if (!$ok) {
            $_SESSION['_login_fail'] = ($_SESSION['_login_fail'] ?? 0) + 1;
            if ($_SESSION['_login_fail'] >= self::MAX_ATTEMPTS) {
                $_SESSION['_login_lock'] = time() + self::LOCK_SECONDS;
                $_SESSION['_login_fail'] = 0;
            }
            return false;
        }

        session_regenerate_id(true);
        unset($_SESSION['_login_fail'], $_SESSION['_login_lock']);
        $_SESSION['user'] = [
            'id'    => (int) $user['id_user'],
            'nama'  => $user['nama'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        Audit::log('LOGIN', 'users', (int) $user['id_user']);
        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('LOGOUT', 'users', self::user()['id']);
        }
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function homeFor(string $role): string
    {
        return match ($role) {
            'superadmin' => 'admin/superadmin',
            'admin'      => 'admin',
            default      => '',
        };
    }
}

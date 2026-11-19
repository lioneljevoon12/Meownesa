<?php
/**
 * Konfigurasi aplikasi. Nilai dibaca dari file .env (lihat .env.example);
 * default di bawah cocok untuk XAMPP lokal.
 */

$env = [];
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
}

$get = static fn (string $key, string $default = '') => $env[$key] ?? $default;

return [
    'app' => [
        'name'  => 'MeowNesa',
        'env'   => $get('APP_ENV', 'local'),          // local | production
        'debug' => $get('APP_DEBUG', 'true') === 'true',
        'timezone' => 'Asia/Jakarta',
        // Nomor CP Jaga Satwa untuk tautan wa.me (format internasional tanpa +)
        'cp_whatsapp' => $get('CP_WHATSAPP', '6281234567890'),
        'laporan_url' => $get('LAPORAN_URL', 'https://forms.gle/'),
    ],
    'db' => [
        'host' => $get('DB_HOST', '127.0.0.1'),
        'port' => $get('DB_PORT', '3306'),
        'name' => $get('DB_NAME', 'meownesa_uas'),
        'user' => $get('DB_USER', 'root'),
        'pass' => $get('DB_PASS', ''),
    ],
    'seed_password' => $get('SEED_PASSWORD', 'admin123'),
];

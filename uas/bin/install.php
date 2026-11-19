<?php
declare(strict_types=1);

/**
 * Installer CLI: membuat database, tabel, akun awal, dan data contoh.
 *
 *   php bin/install.php          -> buat DB jika belum ada, pasang skema + seed
 *   php bin/install.php --fresh  -> hapus semua tabel lalu pasang ulang
 *
 * Skema & seed ada di uas/database (mandiri, terpisah dari versi Laravel).
 */

if (PHP_SAPI !== 'cli') {
    exit('Jalankan lewat CLI.');
}

require __DIR__ . '/../app/bootstrap.php';

$c = config('db');
$root = dirname(__DIR__) . '/database';

try {
    $server = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $server->exec("USE `{$c['name']}`");

    $existing = (int) $server->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $server->quote($c['name']))->fetchColumn();
    if ($existing > 0 && !in_array('--fresh', $argv, true)) {
        fwrite(STDERR, "Database '{$c['name']}' sudah berisi {$existing} tabel. Pakai --fresh untuk memasang ulang (data hilang).\n");
        exit(1);
    }

    echo "1/3 Memasang skema (16 tabel)...\n";
    $server->exec(file_get_contents($root . '/schema.sql'));

    echo "2/3 Membuat akun awal...\n";
    $now = date('Y-m-d H:i:s');
    $hash = password_hash(config('seed_password'), PASSWORD_BCRYPT, ['cost' => 12]);
    $ins = $server->prepare('INSERT INTO users (id_user, nama, email, password_hash, role, status_akun, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
    foreach ([
        [1, 'Super Admin MeowNesa',       'superadmin@unesa.ac.id',          'superadmin'],
        [2, 'Admin Jaga Satwa UNESA',     'admin@jagasatwa.unesa.ac.id',     'admin'],
        [3, 'Relawan Feeder Ketintang',   'relawan.ketintang@unesa.ac.id',   'admin'],
    ] as [$id, $nama, $email, $role]) {
        $ins->execute([$id, $nama, $email, $hash, $role, 'aktif', $now, $now]);
    }

    echo "3/3 Mengisi data contoh...\n";
    $server->exec(file_get_contents($root . '/seed.sql'));

    echo "\nSelesai. Database: {$c['name']}\n";
    echo "Login: superadmin@unesa.ac.id | admin@jagasatwa.unesa.ac.id  (password: sesuai SEED_PASSWORD)\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Gagal: ' . $e->getMessage() . "\n");
    exit(1);
}

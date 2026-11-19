<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/** Koneksi PDO tunggal. Semua query memakai prepared statement. */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** Insert baris dan kembalikan id baru. created_at/updated_at diisi otomatis. */
    public static function insert(string $table, array $data): int
    {
        $data += ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        $cols = implode(',', array_map(static fn ($c) => "`$c`", array_keys($data)));
        $marks = implode(',', array_fill(0, count($data), '?'));
        self::exec("INSERT INTO `$table` ($cols) VALUES ($marks)", array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, string $pk, int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $set = implode(',', array_map(static fn ($c) => "`$c` = ?", array_keys($data)));
        return self::exec("UPDATE `$table` SET $set WHERE `$pk` = ?", [...array_values($data), $id]);
    }
}

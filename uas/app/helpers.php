<?php
declare(strict_types=1);

use App\Core\Csrf;

/** Ambil nilai konfigurasi: config('db.host'). */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escape HTML untuk output (cegah XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Path dasar aplikasi, mendukung deploy di subfolder (mis. /meownesa). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir === '/' || $dir === '.' ? '' : $dir, '/');
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** URL untuk gambar yang disimpan di DB (aset bawaan atau hasil upload). */
function img(?string $path): string
{
    if (!$path) {
        return asset('img/brand/icon-head.png');
    }
    return str_starts_with($path, 'http') ? $path : url($path);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/** Flash message satu kali tampil. */
function flash(string $type, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$type] = $message;
        return null;
    }
    $msg = $_SESSION['_flash'][$type] ?? null;
    unset($_SESSION['_flash'][$type]);
    return $msg;
}

/** Simpan input lama agar form tidak kosong saat validasi gagal. */
function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function tanggal_id(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($date);
    return date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Label & warna badge untuk status kucing. */
function status_kucing_meta(string $status): array
{
    return match ($status) {
        'Tersedia'         => ['Siap Diadopsi',     'success'],
        'Proses Screening' => ['Sedang Diproses',   'warning'],
        'Diadopsi'         => ['Sudah Diadopsi',    'primary'],
        'Almarhum'         => ['Almarhum',          'secondary'],
        default            => ['Terdata Kampus',    'info'],
    };
}

function status_badge(string $status): string
{
    [$label, $tone] = status_kucing_meta($status);
    return '<span class="badge text-bg-' . $tone . '">' . e($label) . '</span>';
}

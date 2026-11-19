<?php
declare(strict_types=1);

namespace App\Core;

/** Router sederhana: pola /kucing/{id}, middleware per-route, CSRF global untuk POST. */
final class Router
{
    /** @var array<int, array{string, string, callable, array}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = ['GET', $pattern, $handler, $middleware];
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = ['POST', $pattern, $handler, $middleware];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = '/' . trim(substr($path, strlen($base)), '/');
        }
        $path = $path === '//' ? '/' : $path;

        foreach ($this->routes as [$m, $pattern, $handler, $middleware]) {
            if ($m !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            if ($method === 'POST' && !Csrf::verify()) {
                $this->abort(419, 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
            }
            foreach ($middleware as $mw) {
                $mw();
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler(...array_values($params));
            return;
        }

        $this->abort(404, 'Halaman tidak ditemukan.');
    }

    public function abort(int $code, string $message): never
    {
        http_response_code($code);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            json_response(['ok' => false, 'message' => $message], $code);
        }
        View::render('error', ['code' => $code, 'message' => $message]);
        exit;
    }
}

<?php
declare(strict_types=1);

// Dengan `php -S`, biarkan file statis (css/js/gambar) dilayani langsung.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;

$router = new Router();
require APP_PATH . '/routes.php';

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    error_log((string) $e);
    $router->abort(500, config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server.');
}

<?php

use App\Http\Middleware\CacheDataPublik;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\RecordAuditLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'  => CheckRole::class,
            'audit' => RecordAuditLog::class,
            'cache.publik' => CacheDataPublik::class,
        ]);

        // Aplikasi ini API-only: tamu tidak pernah di-redirect ke halaman login (tidak ada route 'login'),
        // melainkan mendapat 401 JSON — juga bila klien lupa mengirim header Accept: application/json.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua error di /api/* selalu berbentuk JSON (bukan redirect ke halaman login).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Tidak terautentikasi. Silakan login terlebih dahulu.'], 401)
            : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Data atau endpoint tidak ditemukan.'], 404)
            : null);

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.'], 429, $e->getHeaders())
            : null);
    })->create();

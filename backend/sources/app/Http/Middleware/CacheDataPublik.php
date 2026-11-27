<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache HTTP untuk data PUBLIK yang jarang berubah (katalog, lokasi, artikel, peta). Pemakaian di route:
 * ->middleware('cache.publik') atau 'cache.publik:120' (detik).
 *
 * Menambahkan ETag (hash isi) dan Cache-Control publik; bila klien mengirim `If-None-Match` yang cocok, respons
 * menjadi 304 tanpa isi. Untuk PWA ini menghemat kuota dan membuat halaman tetap cepat di jaringan kampus yang
 * lambat. Hanya respons 200 yang disimpan: error (404/422) tidak pernah di-cache.
 *
 * Jangan dipasang pada data pribadi atau yang bergantung pada pengguna.
 */
class CacheDataPublik
{
    public function handle(Request $request, Closure $next, int $detik = 60): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200 || ! $response->getContent()) {
            return $response;
        }

        $response->setEtag(md5((string) $response->getContent()));
        $response->setPublic();
        $response->setMaxAge($detik);
        $response->isNotModified($request);   // mengubah respons menjadi 304 bila If-None-Match cocok

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\TrustedHosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cookie sesi terikat ke nama host: login di http://localhost lalu membuka
 * http://127.0.0.1 memakai wadah cookie berbeda, sehingga sesi tampak "hilang".
 * Alamat alias loopback karenanya dialihkan ke host pada APP_URL agar pengguna
 * selalu berada di satu host.
 *
 * Hanya GET/HEAD, hanya alias loopback (host tepercaya tambahan di
 * CSIRT_TRUSTED_HOSTS tidak dialihkan), dan health check /up dikecualikan.
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('csirt.canonical_redirect') || ! $request->isMethodSafe() || $request->is('up')) {
            return $next($request);
        }

        $canonical = parse_url((string) config('app.url'));
        $canonicalHost = strtolower($canonical['host'] ?? '');
        $host = strtolower($request->getHost());

        if ($canonicalHost === '' || $host === $canonicalHost || ! in_array($host, TrustedHosts::LOOPBACK, true)) {
            return $next($request);
        }

        return redirect()->away(rtrim((string) config('app.url'), '/') . $request->getRequestUri());
    }
}

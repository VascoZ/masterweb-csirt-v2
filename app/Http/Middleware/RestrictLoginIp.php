<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya IP/CIDR pada CSIRT_LOGIN_WHITELIST yang boleh membuka dan memproses login.
 */
class RestrictLoginIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $whitelist = config('csirt.login_whitelist', []);

        if (in_array('*', $whitelist, true) || IpUtils::checkIp((string) $request->ip(), $whitelist)) {
            return $next($request);
        }

        // Dicatat di log keamanan (bukan database) agar pemindaian tidak membanjiri audit log.
        AuditLogger::security('login_ip_blocked', [
            'path' => '/' . ltrim($request->path(), '/'),
            'method' => $request->method(),
        ]);

        abort(404);
    }
}

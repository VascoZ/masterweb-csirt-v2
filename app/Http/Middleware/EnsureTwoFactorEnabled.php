<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bila CSIRT_ENFORCE_2FA aktif, pengguna dashboard wajib mengaktifkan 2FA
 * sebelum dapat mengakses halaman lain selain Keamanan Akun.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('csirt.enforce_2fa') || ! $user || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->routeIs('dashboard.security.*')) {
            return $next($request);
        }

        return redirect()->route('dashboard.security.edit')
            ->with('warning', 'Aktifkan autentikasi dua faktor (2FA) terlebih dahulu untuk mengakses dashboard.');
    }
}

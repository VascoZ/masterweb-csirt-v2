<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batas umur sesi absolut sejak login, terlepas dari aktivitas
 * (OWASP ASVS V7, NIST SP 800-63B §2.2.3 reautentikasi berkala).
 * SESSION_LIFETIME hanya mengatur batas tidak aktif (idle).
 */
class EnforceAbsoluteSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $startedAt = $request->session()->get('auth.started_at');
        $limit = (int) config('csirt.session_absolute_minutes');

        if ($user && $limit > 0) {
            if (! $startedAt) {
                $request->session()->put('auth.started_at', now()->timestamp);
            } elseif (now()->timestamp - $startedAt > $limit * 60) {
                AuditLogger::log('session_expired', $user, description: 'Sesi berakhir (batas absolut ' . $limit . ' menit)');
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('warning', 'Sesi Anda telah berakhir. Silakan login kembali.');
            }
        }

        return $next($request);
    }
}

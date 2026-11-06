<?php

use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\EnsureTicketsEnabled;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\RestrictLoginIp;
use App\Http\Middleware\SecurityHeaders;
use App\Support\AuditLogger;
use App\Support\TrustedHosts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Csp\AddCspHeaders;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hanya host yang dikenal yang diterima (mencegah Host header poisoning):
        // host APP_URL, loopback, dan CSIRT_TRUSTED_HOSTS. Lihat App\Support\TrustedHosts.
        // Otomatis nonaktif di environment local & saat unit test.
        $middleware->trustHosts(at: fn () => TrustedHosts::patterns(), subdomains: false);

        // Satu host kanonik agar cookie sesi tidak terpecah antara localhost dan 127.0.0.1.
        $middleware->web(prepend: [RedirectToCanonicalHost::class]);

        $middleware->web(append: [
            SecurityHeaders::class,
            AddCspHeaders::class,
        ]);

        $middleware->alias([
            'login.ip' => RestrictLoginIp::class,
            '2fa' => EnsureTwoFactorEnabled::class,
            'active' => EnsureUserIsActive::class,
            'tickets.open' => EnsureTicketsEnabled::class,
            'session.absolute' => EnforceAbsoluteSessionLifetime::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'recovery_code']);

        // Catat setiap penolakan akses oleh pengguna yang login (OWASP ASVS V16, NIST AU-2).
        $logDenied = function (Throwable $e, Request $request) {
            // Satu catatan per request walau exception dipetakan ke tipe lain.
            if ($request->user() && ! $request->attributes->get('audit.access_denied')) {
                $request->attributes->set('audit.access_denied', true);
                AuditLogger::log('access_denied', null, null, null, 'Akses ditolak: ' . $request->method() . ' /' . ltrim($request->path(), '/'));
            }

            return null;
        };
        $exceptions->render(fn (AuthorizationException $e, Request $request) => $logDenied($e, $request));
        $exceptions->render(fn (AccessDeniedHttpException $e, Request $request) => $logDenied($e, $request));
    })->create();

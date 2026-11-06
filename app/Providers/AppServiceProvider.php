<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Settings;
use App\Support\SiteData;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Di belakang reverse proxy (Nginx/Docker) agar IP klien & HTTPS terbaca benar.
        if ($proxies = config('csirt.trusted_proxies')) {
            TrustProxies::at($proxies === ['*'] ? '*' : $proxies);
        }

        $this->forceHttpsScheme();

        // Pengaturan dari dashboard menimpa default .env (lihat App\Support\Settings).
        Settings::applyOverrides();

        // OWASP ASVS V6 / NIST SP 800-63B: minimal 12 karakter, maks. 72 byte (batas
        // bcrypt; lebih dari itu terpotong diam-diam), serta cek kebocoran di produksi.
        Password::defaults(fn () => Password::min(12)
            ->max(72)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->when(app()->isProduction(), fn ($rule) => $rule->uncompromised()));

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('superadmin', fn (User $user) => $user->isSuperadmin());

        $this->configureRateLimiting();

        // Data identitas situs (logo, profil, footer, dll.) untuk semua view web.
        // SiteData di-cache & di-memo, jadi hanya di-query sekali per request.
        View::composer(['layouts.*', 'pages.*', 'auth.*', 'dashboard.*'], function ($view) {
            $view->with('site', SiteData::all());
        });

        // Aset Vite ikut membawa nonce CSP yang sama dengan request ini.
        View::composer(['layouts.*', 'dashboard.layouts.*', 'errors.*'], fn () => Vite::useCspNonce(app('csp-nonce')));
    }

    /**
     * Di produksi dengan APP_URL https, semua URL dibangun ber-https walau TLS
     * diterminasi di proxy. Header Host sendiri divalidasi oleh middleware trustHosts
     * (App\Support\TrustedHosts), jadi root URL tidak perlu dipaksa; membiarkannya
     * mengikuti host permintaan menjaga cookie sesi tetap valid saat diakses lewat
     * alias yang sah (localhost, alamat IP terdaftar).
     */
    public function forceHttpsScheme(): void
    {
        if (app()->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')) . '|' . $request->ip();

            return [Limit::perMinute(5)->by($key), Limit::perMinute(20)->by($request->ip())];
        });

        // Dibatasi per akun yang sedang diverifikasi (bukan hanya per IP), agar kode 6 digit
        // tidak bisa ditebak lewat banyak IP. Struktur sesi: login = [id, expires_at].
        RateLimiter::for('two-factor', fn (Request $request) => [
            Limit::perMinute(5)->by('2fa-user:' . data_get($request->session()->get('login'), 'id', 'none')),
            Limit::perMinute(20)->by('2fa-ip:' . $request->ip()),
        ]);

        RateLimiter::for('tickets', fn (Request $request) => Limit::perMinutes(10, 3)->by($request->ip()));

        RateLimiter::for('ticket-reply', fn (Request $request) => Limit::perMinutes(10, 5)->by($request->ip()));

        RateLimiter::for('ticket-status', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}

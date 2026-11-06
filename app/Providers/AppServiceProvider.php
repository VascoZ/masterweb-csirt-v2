<?php

namespace App\Providers;

use App\Models\User;
use App\Support\SiteData;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        Password::defaults(fn () => Password::min(8)
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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')) . '|' . $request->ip();

            return [Limit::perMinute(5)->by($key), Limit::perMinute(20)->by($request->ip())];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id') . '|' . $request->ip()));

        RateLimiter::for('tickets', fn (Request $request) => Limit::perMinutes(10, 3)->by($request->ip()));

        RateLimiter::for('ticket-status', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        // Redirect eksplisit ke form: "back()" bisa mengarah ke URL gambar captcha
        // karena gambar itu adalah request GET terakhir yang dicatat sesi.
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'captcha'],
        ], [
            'captcha.captcha' => 'Kode captcha tidak sesuai.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('login')->withErrors($validator)->withInput($request->only('email'));
        }

        $credentials = $validator->validated();
        $user = User::where('email', $credentials['email'])->first();

        // Selalu jalankan verifikasi hash, termasuk bila email tidak terdaftar, agar
        // waktu respons tidak membocorkan email mana yang ada (CWE-208).
        $passwordValid = Hash::check($credentials['password'], $user?->password ?? self::dummyHash());

        if (! $user || ! $passwordValid) {
            AuditLogger::log('login_failed', null, null, ['email' => $credentials['email']], 'Login gagal');

            return redirect()->route('login')->withInput($request->only('email'))->with('error', 'Email atau password salah.');
        }

        if (! $user->is_active) {
            AuditLogger::log('login_failed', $user, description: 'Login ditolak: akun nonaktif', userId: $user->id);

            return redirect()->route('login')->withInput($request->only('email'))->with('error', 'Akun Anda dinonaktifkan. Hubungi superadmin.');
        }

        // Akun dengan 2FA: simpan sementara di sesi, selesaikan di halaman tantangan.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->regenerate();
            $request->session()->put('login', ['id' => $user->id, 'expires_at' => now()->addMinutes(5)->timestamp]);

            return redirect()->route('two-factor.create');
        }

        return self::completeLogin($request, $user);
    }

    private static function dummyHash(): string
    {
        static $hash;

        return $hash ??= Hash::make(Str::random(40));
    }

    public static function completeLogin(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('login');
        $request->session()->put('auth.started_at', now()->timestamp);

        Sessions::logoutOtherDevices($user);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        AuditLogger::log('login', $user, description: 'Login berhasil');

        return redirect()->intended(route('dashboard.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            AuditLogger::log('logout', $user, description: 'Logout');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function reloadCaptcha()
    {
        return response()->json(['captcha' => captcha_src()]);
    }
}

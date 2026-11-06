<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use App\Support\Sessions;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Halaman Keamanan Akun: ganti password dan pengelolaan 2FA (TOTP).
 */
class SecurityController extends Controller
{
    public function edit(Request $request, TwoFactor $twoFactor): View
    {
        $user = $request->user();
        $pendingSecret = $request->session()->get('2fa.pending_secret');

        return view('dashboard.security.edit', [
            'user' => $user,
            'pendingSecret' => $pendingSecret,
            'qrCode' => $pendingSecret ? $twoFactor->qrCodeSvg($user, $pendingSecret) : null,
            'recoveryCodes' => $request->session()->get('2fa.recovery_codes'),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        Sessions::logoutOtherDevices($request->user());
        $request->session()->regenerate();

        AuditLogger::log('password_changed', $request->user(), description: 'Password diganti');

        return back()->with('success', 'Password berhasil diganti. Sesi di perangkat lain telah diakhiri.');
    }

    /**
     * Langkah 1: buat secret sementara dan tampilkan QR code.
     */
    public function enableTwoFactor(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->session()->put('2fa.pending_secret', $twoFactor->generateSecret());

        return redirect()->route('dashboard.security.edit');
    }

    /**
     * Langkah 2: aktifkan setelah pengguna membuktikan aplikasinya menghasilkan kode yang benar.
     */
    public function confirmTwoFactor(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $secret = $request->session()->get('2fa.pending_secret');
        $user = $request->user();

        if (! $secret || ! $twoFactor->verify($user, $request->input('code'), $secret)) {
            return back()->with('error', 'Kode tidak valid. Pastikan jam perangkat sudah sinkron lalu coba lagi.');
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('2fa.pending_secret');
        $request->session()->flash('2fa.recovery_codes', $codes);

        AuditLogger::log('2fa_enabled', $user, description: '2FA diaktifkan');

        return redirect()->route('dashboard.security.edit')->with('success', '2FA berhasil diaktifkan. Simpan kode pemulihan di tempat aman.');
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $codes = $twoFactor->generateRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $request->session()->flash('2fa.recovery_codes', $codes);

        AuditLogger::log('2fa_recovery_regenerated', $request->user(), description: 'Kode pemulihan 2FA dibuat ulang');

        return back()->with('success', 'Kode pemulihan baru telah dibuat. Kode lama tidak berlaku lagi.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        if (config('csirt.enforce_2fa')) {
            return back()->with('error', '2FA diwajibkan oleh kebijakan sistem dan tidak dapat dinonaktifkan.');
        }

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        AuditLogger::log('2fa_disabled', $request->user(), description: '2FA dinonaktifkan');

        return back()->with('success', '2FA telah dinonaktifkan.');
    }
}

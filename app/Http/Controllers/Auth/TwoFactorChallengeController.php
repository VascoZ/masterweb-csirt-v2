<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function store(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $valid = filled($data['code'] ?? null)
            ? $twoFactor->verify($user, $data['code'])
            : $twoFactor->useRecoveryCode($user, $data['recovery_code']);

        if (! $valid) {
            AuditLogger::log('2fa_failed', $user, description: 'Kode 2FA salah', userId: $user->id);

            return back()->with('error', 'Kode autentikasi tidak valid.');
        }

        if (blank($data['code'] ?? null)) {
            AuditLogger::log('2fa_recovery_used', $user, description: 'Login memakai kode pemulihan', userId: $user->id);
        }

        return LoginController::completeLogin($request, $user);
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('login');

        if (! $pending || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('login');

            return null;
        }

        return User::find($pending['id']);
    }
}

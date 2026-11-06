<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('dashboard.users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('dashboard.users.form', ['user' => new User()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate($this->rules() + ['password' => ['required', Password::defaults()]]);

        $user = new User($data);
        $user->is_admin = $request->boolean('is_admin');
        $user->save();

        return redirect()->route('dashboard.users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('dashboard.users.form', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate($this->rules($user) + ['password' => ['nullable', Password::defaults()]]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->fill($data);

        if ($request->user()->can('changeRole', $user)) {
            $user->is_admin = $request->boolean('is_admin');

            if ($request->boolean('reset_2fa') && $user->hasTwoFactorEnabled()) {
                $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null]);
                AuditLogger::log('2fa_reset', $user, description: '2FA pengguna direset oleh superadmin');
            }
        }

        $user->save();

        return redirect()->route('dashboard.users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
        ];
    }
}

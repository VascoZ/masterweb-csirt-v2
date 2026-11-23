<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Sessions;
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
        $user->is_active = $request->boolean('is_active', true);
        $user->save();

        return redirect()->route('dashboard.users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(string $user): View
    {
        $target = User::where('username', $user)->firstOrFail();
        $this->authorize('update', $target);

        return view('dashboard.users.form', ['user' => $target]);
    }

    public function update(Request $request, string $user): RedirectResponse
    {
        $target = User::where('username', $user)->firstOrFail();
        $this->authorize('update', $target);

        $data = $request->validate($this->rules($target) + ['password' => ['nullable', Password::defaults()]]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $target->fill($data);
        $terminateSessions = isset($data['password']);

        if ($request->user()->can('changeRole', $target)) {
            $target->is_admin = $request->boolean('is_admin');

            if ($request->has('is_active')) {
                $target->is_active = $request->boolean('is_active');
                $terminateSessions = $terminateSessions || ! $target->is_active;
            }

            if ($request->boolean('reset_2fa') && $target->hasTwoFactorEnabled()) {
                $target->resetTwoFactor();
                $terminateSessions = true;
                AuditLogger::log('2fa_reset', $target, description: '2FA pengguna direset oleh superadmin');
            }
        }

        $target->save();

        if ($terminateSessions) {
            Sessions::terminateAll($target);
        }

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

@extends('dashboard.layouts.app')

@section('title', $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')

@section('content')
<form method="post" action="{{ $user->exists ? route('dashboard.users.update', $user) : route('dashboard.users.store') }}" class="col-lg-6">
    @csrf
    @if ($user->exists) @method('put') @endif
    <x-form.input name="name" label="Nama" :value="$user->name" required autofocus />
    <x-form.input name="username" label="Username" :value="$user->username" required help="Huruf, angka, titik, garis bawah, atau tanda hubung." />
    <x-form.input name="email" type="email" label="Email" :value="$user->email" required />
    <x-form.input name="password" type="password" :label="$user->exists ? 'Password baru (kosongkan bila tidak diubah)' : 'Password'"
        :required="! $user->exists" autocomplete="new-password"
        help="Minimal 8 karakter, kombinasi huruf besar, huruf kecil, angka, dan simbol." />

    @if (! $user->exists || auth()->user()->can('changeRole', $user))
        <x-form.checkbox name="is_admin" label="Admin (kelola konten, aduan, dan advisory)" :checked="$user->is_admin" />
        @if ($user->exists && $user->hasTwoFactorEnabled())
            <x-form.checkbox name="reset_2fa" label="Reset 2FA (pengguna harus mendaftarkan ulang aplikasi autentikator)" />
        @endif
    @elseif ($user->exists)
        <p class="small text-muted">Anda tidak dapat mengubah peran akun Anda sendiri.</p>
    @endif

    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route('dashboard.users.index') }}" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection

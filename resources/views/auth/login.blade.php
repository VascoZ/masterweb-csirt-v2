@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="auth-wrap">
    <div class="container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <span class="icon-tile badge-soft-primary mb-3"><i class="bi bi-person-lock"></i></span>
                <h1 class="h3 mb-1">Masuk ke Dashboard</h1>
                <p class="text-secondary small">Khusus petugas {{ \App\Support\SiteData::name() }}</p>
            </div>
            <x-flash />
            <form action="{{ route('login.store') }}" method="post" class="card">
                @csrf
                <div class="card-body p-4">
                    <x-form.input name="email" type="email" label="Email" required autofocus autocomplete="username" />
                    <x-form.input name="password" type="password" label="Password" required autocomplete="current-password" />
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="show-password" data-toggle-password="#password">
                        <label class="form-check-label small" for="show-password">Tampilkan password</label>
                    </div>
                    <x-captcha />
                    <button class="btn btn-primary w-100 btn-lg" type="submit">Masuk</button>
                </div>
            </form>
            <p class="text-center small text-secondary mt-4"><i class="bi bi-shield-lock"></i> Akses tercatat di audit log.</p>
        </div>
    </div>
</div>
@endsection

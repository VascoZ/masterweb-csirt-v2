@extends('layouts.app')

@section('title', 'Verifikasi Dua Langkah')

@section('content')
<div class="auth-wrap">
    <div class="container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <span class="icon-tile badge-soft-primary mb-3"><i class="bi bi-phone"></i></span>
                <h1 class="h3 mb-1">Verifikasi Dua Langkah</h1>
                <p class="text-secondary small">Masukkan 6 digit kode dari aplikasi autentikator Anda.</p>
            </div>
            <x-flash />
            <form action="{{ route('two-factor.store') }}" method="post" class="card">
                @csrf
                <div class="card-body p-4">
                    <x-form.input name="code" label="Kode autentikasi" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" autofocus maxlength="7" class="form-control-lg text-center font-monospace" placeholder="000000" />
                    <details class="mb-3">
                        <summary class="small text-secondary">Tidak bisa mengakses aplikasi autentikator?</summary>
                        <div class="mt-3">
                            <x-form.input name="recovery_code" label="Kode pemulihan" autocomplete="off" class="font-monospace" help="Setiap kode pemulihan hanya dapat dipakai satu kali." />
                        </div>
                    </details>
                    <button class="btn btn-primary w-100 btn-lg" type="submit">Verifikasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

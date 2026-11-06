@extends('dashboard.layouts.app')

@section('title', 'Keamanan Akun')

@section('content')
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-bold"><i class="bi bi-shield-lock"></i> Autentikasi Dua Faktor (2FA)</div>
            <div class="card-body">
                @if ($user->hasTwoFactorEnabled())
                    <p class="text-success"><i class="bi bi-check-circle-fill"></i> 2FA aktif sejak {{ $user->two_factor_confirmed_at->translatedFormat('d F Y H:i') }}.</p>

                    @if ($recoveryCodes)
                        <div class="alert alert-warning">
                            <p class="fw-bold mb-2">Simpan kode pemulihan berikut di tempat aman. Kode ini hanya ditampilkan sekali.</p>
                            <ul class="list-unstyled recovery-codes mb-0 user-select-all">
                                @foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach
                            </ul>
                        </div>
                    @else
                        <p class="small text-muted">Sisa kode pemulihan: {{ count($user->two_factor_recovery_codes ?? []) }}.</p>
                    @endif

                    <form method="post" action="{{ route('dashboard.security.2fa.recovery') }}" class="mb-3">
                        @csrf
                        <div class="input-group input-group-sm">
                            <input type="password" name="current_password" class="form-control" placeholder="Password saat ini" required autocomplete="current-password" aria-label="Password saat ini">
                            <button class="btn btn-outline-secondary">Buat ulang kode pemulihan</button>
                        </div>
                    </form>

                    @unless (config('csirt.enforce_2fa'))
                        <form method="post" action="{{ route('dashboard.security.2fa.disable') }}">
                            @csrf
                            @method('delete')
                            <div class="input-group input-group-sm">
                                <input type="password" name="current_password" class="form-control" placeholder="Password saat ini" required autocomplete="current-password" aria-label="Password saat ini">
                                <button class="btn btn-outline-danger" data-confirm="Nonaktifkan 2FA? Akun Anda menjadi kurang aman.">Nonaktifkan 2FA</button>
                            </div>
                        </form>
                    @endunless
                @elseif ($pendingSecret)
                    <ol class="small">
                        <li>Pindai QR code berikut dengan aplikasi autentikator (Google Authenticator, Microsoft Authenticator, Aegis, FreeOTP, dll.).</li>
                        <li>Masukkan 6 digit kode yang muncul untuk mengonfirmasi.</li>
                    </ol>
                    <div class="qr-code bg-white p-2 d-inline-block border rounded mb-2">{!! $qrCode !!}</div>
                    <p class="small">Atau masukkan kunci manual: <code class="user-select-all">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</code></p>
                    <form method="post" action="{{ route('dashboard.security.2fa.confirm') }}">
                        @csrf
                        <x-form.input name="code" label="Kode autentikasi" inputmode="numeric" autocomplete="one-time-code" required maxlength="7" autofocus />
                        <button class="btn btn-primary">Konfirmasi & Aktifkan</button>
                    </form>
                @else
                    <p>2FA menambahkan lapisan keamanan dengan meminta kode dari aplikasi autentikator setiap login.</p>
                    @if (config('csirt.enforce_2fa'))
                        <p class="text-danger small">Kebijakan sistem mewajibkan 2FA untuk semua akun dashboard.</p>
                    @endif
                    <form method="post" action="{{ route('dashboard.security.2fa.enable') }}">
                        @csrf
                        <button class="btn btn-primary"><i class="bi bi-qr-code"></i> Aktifkan 2FA</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <form method="post" action="{{ route('dashboard.security.password') }}" class="card">
            @csrf
            @method('put')
            <div class="card-header fw-bold"><i class="bi bi-key"></i> Ganti Password</div>
            <div class="card-body">
                <x-form.input name="current_password" type="password" label="Password saat ini" required autocomplete="current-password" />
                <x-form.input name="password" type="password" label="Password baru" required autocomplete="new-password"
                    help="Minimal 12 karakter, kombinasi huruf besar, huruf kecil, angka, dan simbol." />
                <x-form.input name="password_confirmation" type="password" label="Ulangi password baru" required autocomplete="new-password" />
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="show-passwords" data-toggle-password="#current_password, #password, #password_confirmation">
                    <label class="form-check-label" for="show-passwords">Tampilkan password</label>
                </div>
                <button class="btn btn-primary">Ganti Password</button>
            </div>
        </form>
    </div>
</div>
@endsection

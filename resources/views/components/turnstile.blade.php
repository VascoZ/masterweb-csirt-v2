@php($siteKey = config('turnstile.site_key'))

@if (config('turnstile.disabled'))
    {{-- Verifikasi dinonaktifkan pada lingkungan uji. --}}
@elseif (blank($siteKey))
    <div class="alert alert-warning small mb-3">Verifikasi keamanan belum dikonfigurasi.</div>
@else
    <div class="mb-3">
        <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-theme="light"></div>
        @error('cf-turnstile-response')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif

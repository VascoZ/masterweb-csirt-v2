{{-- Tidak memakai data database agar tetap tampil saat database bermasalah. --}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('code') · {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss'])
</head>
<body class="bg-light">
    <main class="container d-flex flex-column align-items-center justify-content-center text-center min-vh-100">
        <div class="display-1 fw-bold text-primary">@yield('code')</div>
        <h1 class="h3 mb-3">@yield('title')</h1>
        <p class="text-muted mb-4">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn btn-primary">Kembali ke Beranda</a>
    </main>
</body>
</html>

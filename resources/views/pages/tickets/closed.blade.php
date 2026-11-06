@extends('layouts.app')

@section('title', 'Aduan Ditutup Sementara')

@section('content')
<div class="auth-wrap">
    <div class="container">
        <div class="card result-card mx-auto text-center border-0 shadow-lg">
            <div class="card-body p-4 p-lg-5">
                <span class="icon-tile badge-soft-warning mx-auto mb-3"><i class="bi bi-pause-circle"></i></span>
                <h1 class="h3">Penerimaan aduan ditutup sementara</h1>
                <p class="text-secondary">Form aduan online sedang tidak menerima laporan baru. Untuk insiden mendesak, hubungi kami melalui kontak berikut.</p>
                @if ($footer = $site['footer'])
                    <ul class="list-unstyled small mb-4">
                        <li><i class="bi bi-envelope"></i> <a href="mailto:{{ $footer->email }}">{{ $footer->email }}</a></li>
                        <li><i class="bi bi-telephone"></i> {{ $footer->telephone }}</li>
                    </ul>
                @endif
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ route('tickets.status.form') }}" class="btn btn-primary">Cek Status Aduan</a>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">Kembali ke Beranda</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

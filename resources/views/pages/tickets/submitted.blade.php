@extends('layouts.app')

@section('title', 'Aduan Terkirim')

@section('content')
<div class="auth-wrap">
    <div class="container">
        <div class="card result-card mx-auto text-center border-0 shadow-lg">
            <div class="card-body p-4 p-lg-5">
                <span class="icon-tile badge-soft-success mx-auto mb-3"><i class="bi bi-check-lg"></i></span>
                <h1 class="h3">Aduan Anda telah diterima</h1>
                <p class="text-secondary mb-4">
                    @if (config('csirt.mail_notifications'))
                        Simpan nomor tiket berikut. Tanda terima juga dikirim ke email Anda.
                    @else
                        <strong>Simpan nomor tiket berikut.</strong> Nomor ini tidak dikirim lewat email dan diperlukan untuk mengecek status aduan.
                    @endif
                </p>
                <div class="ticket-number user-select-all mb-4">{{ $number }}</div>
                <p class="small text-secondary mb-4">Gunakan nomor tiket dan email pelapor untuk memantau status penanganan.</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ route('tickets.status.form') }}" class="btn btn-primary">Cek Status Aduan</a>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">Kembali ke Beranda</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

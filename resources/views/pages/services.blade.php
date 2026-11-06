@extends('layouts.app')

@section('title', 'Layanan')
@section('heading', 'Layanan ' . \App\Support\SiteData::name())
@section('lead', 'Layanan yang kami berikan dalam penanggulangan dan pemulihan insiden siber.')

@section('content')
<div class="container">
    <div class="prose-column">
        @forelse ($services as $service)
            <section class="mb-5">
                @if ($services->count() > 1)<h2 class="h4 mb-3">{{ $service->name }}</h2>@endif
                <div class="rich-content">{!! \App\Support\HtmlSanitizer::clean($service->content) !!}</div>
            </section>
        @empty
            <div class="empty-state"><i class="bi bi-briefcase"></i>Informasi layanan belum diisi.</div>
        @endforelse

        <div class="card border-0 bg-primary-subtle mt-4">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-bold text-primary-emphasis">Mengalami insiden siber?</div>
                    <div class="small text-primary-emphasis">Laporkan sekarang dan dapatkan nomor tiket untuk memantau penanganannya.</div>
                </div>
                <a href="{{ route('tickets.create') }}" class="btn btn-primary"><i class="bi bi-megaphone-fill me-1"></i> Lapor Insiden</a>
            </div>
        </div>
    </div>
</div>
@endsection

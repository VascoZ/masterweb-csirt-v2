@extends('layouts.app')

@section('content')
@php($reportUrl = route('tickets.create'))

<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="eyebrow mb-3"><i class="bi bi-shield-check"></i> Tim Tanggap Insiden Siber</span>
                <h1 class="mb-3 text-balance">{{ \App\Support\SiteData::name() }}</h1>
                @if ($site['profile'])
                    <p class="lead mb-4">{{ \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags($site['profile']->content))), 240) }}</p>
                @endif
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ $reportUrl }}" class="btn btn-danger btn-lg"><i class="bi bi-megaphone-fill me-1"></i> Laporkan Insiden</a>
                    <a href="{{ route('tickets.status.form') }}" class="btn btn-outline-light btn-lg">Cek Status Aduan</a>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
                <div class="hero-panel">
                    <div class="hero-panel-item">
                        <span class="icon-tile"><i class="bi bi-1-circle"></i></span>
                        <div><strong>Laporkan</strong><small>Isi form aduan dan lampirkan bukti. Anda akan menerima nomor tiket.</small></div>
                    </div>
                    <div class="hero-panel-item">
                        <span class="icon-tile"><i class="bi bi-2-circle"></i></span>
                        <div><strong>Verifikasi & analisis</strong><small>Tim kami memverifikasi laporan dan menganalisis dampaknya.</small></div>
                    </div>
                    <div class="hero-panel-item">
                        <span class="icon-tile"><i class="bi bi-3-circle"></i></span>
                        <div><strong>Penanganan</strong><small>Pantau perkembangan penanganan dengan nomor tiket dan email Anda.</small></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="quick-actions">
    <div class="container">
        <div class="row g-3">
            @foreach ([
                [$reportUrl, 'bi-megaphone', 'danger', 'Lapor Insiden', 'Laporkan serangan, kebocoran data, atau kerentanan.'],
                [route('advisories.index'), 'bi-exclamation-octagon', 'warning', 'Peringatan Keamanan', 'Informasi kerentanan terbaru dan cara mitigasinya.'],
                [route('guidances'), 'bi-journal-text', 'primary', 'Panduan', 'Panduan penanganan insiden siber untuk instansi.'],
            ] as [$url, $icon, $color, $title, $text])
                <div class="col-md-4">
                    <a href="{{ $url }}" class="quick-card">
                        <span class="icon-tile badge-soft-{{ $color }}"><i class="bi {{ $icon }}"></i></span>
                        <div>
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

@if ($advisories->isNotEmpty())
<section class="section">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
            <div>
                <span class="eyebrow">Peringatan Keamanan</span>
                <h2 class="section-title">Advisory terbaru</h2>
            </div>
            <a href="{{ route('advisories.index') }}" class="btn btn-outline-secondary btn-sm">Lihat semua <i class="bi bi-arrow-right"></i></a>
        </div>
        @include('pages.advisories.partials.list', ['advisories' => $advisories])
    </div>
</section>
@endif

<section @class(['section', 'section-muted' => $advisories->isNotEmpty()])>
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
            <div>
                <span class="eyebrow">Artikel</span>
                <h2 class="section-title">Kabar & edukasi keamanan siber</h2>
            </div>
            <a href="{{ route('posts.index') }}" class="btn btn-outline-secondary btn-sm">Semua artikel <i class="bi bi-arrow-right"></i></a>
        </div>
        @include('pages.posts.partials.grid', ['posts' => $posts])
    </div>
</section>
@endsection

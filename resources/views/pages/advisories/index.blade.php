@extends('layouts.app')

@section('title', 'Peringatan Keamanan')
@section('heading', 'Peringatan Keamanan')
@section('lead', 'Informasi kerentanan dan ancaman siber beserta rekomendasi penanganannya.')

@section('content')
<div class="container">
    <form class="card card-body mb-4" action="{{ route('advisories.index') }}">
        <div class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="search" class="form-control border-start-0" value="{{ request('search') }}" placeholder="Cari judul, kode, atau CVE..." aria-label="Cari advisory">
                </div>
            </div>
            <div class="col-md-4">
                <select name="severity" class="form-select" aria-label="Tingkat keparahan">
                    <option value="">Semua tingkat keparahan</option>
                    @foreach (\App\Enums\Severity::cases() as $case)
                        <option value="{{ $case->value }}" @selected($severity === $case)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary">Terapkan</button></div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <p class="small text-secondary mb-0"><i class="bi bi-info-circle"></i> Halaman ini hanya memuat advisory berlabel <x-tlp :tlp="\App\Enums\Tlp::Clear" /> yang boleh disebarluaskan tanpa batasan.</p>
        <div class="d-flex gap-2">
            <a href="{{ route('advisories.feed.atom') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-rss"></i> Atom/RSS</a>
            <a href="{{ route('advisories.feed.json') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-json"></i> JSON Feed</a>
        </div>
    </div>

    @include('pages.advisories.partials.list', ['advisories' => $advisories])
    <div class="d-flex justify-content-center mt-4">{{ $advisories->links() }}</div>
</div>
@endsection

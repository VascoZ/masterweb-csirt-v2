@extends('dashboard.layouts.app')

@section('title', 'Advisory ' . $advisory->code)

@section('content')
<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="{{ route('dashboard.advisories.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    <a href="{{ route('dashboard.advisories.edit', $advisory) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Ubah</a>
    @if ($advisory->isPubliclyVisible())
        <a href="{{ route('advisories.show', $advisory) }}" class="btn btn-outline-success" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Lihat di situs</a>
    @endif
</div>

@unless ($advisory->tlp->isPublic())
    <div class="alert alert-dark"><x-tlp :tlp="$advisory->tlp" /> {{ $advisory->tlp->description() }} Advisory ini tidak tampil di situs publik.</div>
@endunless

<article class="card card-body col-xl-10">
    @include('pages.advisories.partials.body')
    <p class="small text-muted mt-3 mb-0">Dibuat oleh {{ $advisory->author->name }} · {{ $advisory->created_at->translatedFormat('d F Y H:i') }}</p>
</article>
@endsection

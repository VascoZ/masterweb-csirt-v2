@extends('layouts.app')

@section('title', $advisory->code . ' ' . $advisory->title)
@section('description', $advisory->summary)

@section('content')
<div class="page-body pt-5 mt-5">
    <div class="container">
        <article class="prose-column">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('advisories.index') }}">Peringatan Keamanan</a></li>
                    <li class="breadcrumb-item active">{{ $advisory->code }}</li>
                </ol>
            </nav>
            @include('pages.advisories.partials.body')
            <hr class="my-5">
            <a href="{{ route('advisories.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Semua peringatan</a>
        </article>
    </div>
</div>
@endsection

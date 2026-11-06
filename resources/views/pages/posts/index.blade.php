@extends('layouts.app')

@section('title', 'Artikel')
@section('heading', $title)
@section('lead', 'Kabar terbaru, edukasi, dan kegiatan seputar keamanan siber.')

@section('content')
<div class="container">
    <div class="row g-3 align-items-center mb-4">
        <div class="col-lg-6">
            <form action="{{ route('posts.index') }}" role="search">
                @foreach (['category', 'author'] as $filter)
                    @if (request($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
                @endforeach
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control border-start-0" placeholder="Cari artikel..." name="search" value="{{ request('search') }}" aria-label="Cari artikel">
                    <button class="btn btn-primary" type="submit">Cari</button>
                </div>
            </form>
        </div>
        <div class="col-lg-6 d-flex flex-wrap gap-2 justify-content-lg-end">
            <a href="{{ route('posts.index') }}" @class(['btn btn-sm', 'btn-dark' => ! request('category'), 'btn-outline-secondary' => request('category')])>Semua</a>
            @foreach ($site['categories'] as $category)
                <a href="{{ route('posts.index', ['category' => $category->slug]) }}" @class(['btn btn-sm', 'btn-dark' => request('category') === $category->slug, 'btn-outline-secondary' => request('category') !== $category->slug])>{{ $category->name }}</a>
            @endforeach
        </div>
    </div>

    @include('pages.posts.partials.grid', ['posts' => $posts])
    <div class="d-flex justify-content-center mt-5">{{ $posts->links() }}</div>
</div>
@endsection

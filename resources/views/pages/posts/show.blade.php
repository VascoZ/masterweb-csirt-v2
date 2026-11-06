@extends('layouts.app')

@section('title', $post->title)
@section('description', $post->excerpt)

@section('content')
<article class="page-hero pb-4">
    <div class="container">
        <div class="prose-column">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('posts.index') }}">Artikel</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('posts.index', ['category' => $post->category->slug]) }}">{{ $post->category->name }}</a></li>
                </ol>
            </nav>
            <h1 class="text-balance">{{ $post->title }}</h1>
            <div class="d-flex align-items-center gap-2 text-secondary small mt-3">
                <i class="bi bi-person-circle fs-5"></i>
                <a href="{{ route('posts.index', ['author' => $post->author->username]) }}" class="fw-semibold text-body">{{ $post->author->name }}</a>
                <span>·</span>
                <span>{{ ($post->published_at ?? $post->created_at)->translatedFormat('d F Y') }}</span>
            </div>
        </div>
    </div>
</article>

<div class="page-body pt-4">
    <div class="container">
        <div class="prose-column">
            @if ($post->image)
                <figure class="article-cover"><img src="{{ asset('storage/' . $post->image) }}" alt=""></figure>
            @endif
            <div class="rich-content">{!! \App\Support\HtmlSanitizer::clean($post->body) !!}</div>
            <hr class="my-5">
            <a href="{{ route('posts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Semua artikel</a>
        </div>
    </div>
</div>
@endsection

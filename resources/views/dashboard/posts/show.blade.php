@extends('dashboard.layouts.app')

@section('title', 'Pratinjau Artikel')

@section('content')
<div class="col-lg-9">
    <div class="mb-3 d-flex gap-2">
        <a href="{{ route('dashboard.posts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        @can('update', $post)
            <a href="{{ route('dashboard.posts.edit', $post) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Ubah</a>
        @endcan
        @can('delete', $post)
            <x-delete-button :action="route('dashboard.posts.destroy', $post)" class="btn btn-outline-danger" />
        @endcan
    </div>
    <article class="card card-body">
        <h2>{{ $post->title }}</h2>
        <p class="text-muted small">{{ $post->category->name }} · {{ $post->author->name }} · {{ $post->published ? 'Terbit' : 'Draf' }}</p>
        @if ($post->image)
            <img src="{{ asset('storage/' . $post->image) }}" alt="" class="img-fluid rounded mb-3">
        @endif
        <div class="rich-content">{!! \App\Support\HtmlSanitizer::clean($post->body) !!}</div>
    </article>
</div>
@endsection

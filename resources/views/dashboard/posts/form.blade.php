@extends('dashboard.layouts.app')

@section('title', $post->exists ? 'Ubah Artikel' : 'Tulis Artikel')

@section('content')
<div class="col-lg-9">
    <form method="post" action="{{ $post->exists ? route('dashboard.posts.update', $post) : route('dashboard.posts.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($post->exists) @method('put') @endif

        <x-form.input name="title" label="Judul" :value="$post->title" required autofocus
            data-slug-url="{{ route('dashboard.posts.checkSlug') }}" data-slug-target="#slug" />
        <x-form.input name="slug" label="Slug (URL)" :value="$post->slug" required />
        <x-form.select name="category_id" label="Kategori" placeholder="Pilih kategori" required
            :value="$post->category_id" :options="$categories->pluck('name', 'id')" />

        <div class="mb-3">
            <label for="image" class="form-label">Gambar utama</label>
            <img @if ($post->image) src="{{ asset('storage/' . $post->image) }}" @endif alt="" id="image-preview" @class(['img-preview img-fluid d-block mb-2 rounded', 'd-none' => ! $post->image])>
            <input type="file" name="image" id="image" accept="image/png,image/jpeg,image/webp" data-image-preview="#image-preview" @class(['form-control', 'is-invalid' => $errors->has('image')])>
            <div class="form-text">JPG/PNG/WEBP, maks. 2 MB.</div>
            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <x-form.trix name="body" label="Isi artikel" :value="$post->body" />

        @can('publish', \App\Models\Post::class)
            <x-form.checkbox name="published" label="Terbitkan artikel" :checked="$post->published" />
        @endcan

        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('dashboard.posts.index') }}" class="btn btn-outline-secondary">Batal</a>
    </form>
</div>
@endsection

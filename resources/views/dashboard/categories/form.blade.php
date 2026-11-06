@extends('dashboard.layouts.app')

@section('title', $category->exists ? 'Ubah Kategori' : 'Tambah Kategori')

@section('content')
<form method="post" action="{{ $category->exists ? route('dashboard.categories.update', $category) : route('dashboard.categories.store') }}" class="col-lg-6">
    @csrf
    @if ($category->exists) @method('put') @endif
    <x-form.input name="name" label="Nama" :value="$category->name" required autofocus
        data-slug-url="{{ route('dashboard.categories.checkSlug') }}" data-slug-target="#slug" />
    <x-form.input name="slug" label="Slug" :value="$category->slug" required />
    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route('dashboard.categories.index') }}" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection

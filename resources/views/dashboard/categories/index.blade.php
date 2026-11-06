@extends('dashboard.layouts.app')

@section('title', 'Kategori Artikel')

@section('content')
<a href="{{ route('dashboard.categories.create') }}" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> Tambah Kategori</a>
<div class="table-responsive col-lg-8">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>#</th><th>Nama</th><th>Slug</th><th>Jumlah artikel</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $category->name }}</td>
                    <td><code>{{ $category->slug }}</code></td>
                    <td>{{ $category->posts_count }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('dashboard.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <x-delete-button :action="route('dashboard.categories.destroy', $category)" label="" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted">Belum ada kategori.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

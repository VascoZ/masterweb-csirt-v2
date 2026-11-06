@extends('dashboard.layouts.app')

@section('title', 'Artikel')

@section('content')
<a href="{{ route('dashboard.posts.create') }}" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> Tulis Artikel</a>
@cannot('publish', \App\Models\Post::class)
    <p class="small text-muted">Artikel yang Anda tulis disimpan sebagai draf dan akan diterbitkan oleh admin.</p>
@endcannot
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>#</th><th>Judul</th><th>Kategori</th><th>Penulis</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($posts as $post)
                <tr>
                    <td>{{ $posts->firstItem() + $loop->index }}</td>
                    <td>{{ $post->title }}</td>
                    <td>{{ $post->category->name }}</td>
                    <td>{{ $post->author->name }}</td>
                    <td>
                        @if ($post->published)
                            <span class="badge badge-soft-success">Terbit</span>
                        @else
                            <span class="badge badge-soft-secondary">Draf</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('dashboard.posts.show', $post) }}" class="btn btn-sm btn-outline-secondary" title="Lihat"><i class="bi bi-eye"></i></a>
                        @can('update', $post)
                            <a href="{{ route('dashboard.posts.edit', $post) }}" class="btn btn-sm btn-outline-primary" title="Ubah"><i class="bi bi-pencil"></i></a>
                        @endcan
                        @can('delete', $post)
                            <x-delete-button :action="route('dashboard.posts.destroy', $post)" label="" />
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">Belum ada artikel.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $posts->links() }}
@endsection

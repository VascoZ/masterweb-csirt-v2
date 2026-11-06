@extends('dashboard.layouts.app')

@section('title', 'Panduan')

@section('content')
<a href="{{ route('dashboard.guidances.create') }}" class="btn btn-primary mb-3"><i class="bi bi-upload"></i> Unggah Panduan</a>
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Nama</th><th>File</th><th>Ukuran</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($guidances as $guidance)
                <tr>
                    <td>{{ $guidance->name }}</td>
                    <td><a href="{{ asset('storage/' . $guidance->path) }}" target="_blank" rel="noopener">{{ $guidance->file_name }}</a></td>
                    <td>{{ \Illuminate\Support\Number::fileSize($guidance->size, 1) }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('dashboard.guidances.edit', $guidance) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <x-delete-button :action="route('dashboard.guidances.destroy', $guidance)" label="" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">Belum ada panduan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $guidances->links() }}
@endsection

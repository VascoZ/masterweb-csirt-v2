@extends('dashboard.layouts.app')

@section('title', 'Dokumen RFC 2350')

@section('content')
<p class="text-muted small">Dokumen terbaru yang ditampilkan di halaman RFC 2350.</p>
<a href="{{ route('dashboard.files.create') }}" class="btn btn-primary mb-3"><i class="bi bi-upload"></i> Unggah Dokumen</a>
<div class="table-responsive col-lg-9">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Nama file</th><th>Diunggah</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($files as $file)
                <tr>
                    <td><a href="{{ asset('storage/' . $file->path) }}" target="_blank" rel="noopener">{{ $file->name }}</a> @if ($loop->first)<span class="badge badge-soft-success">aktif</span>@endif</td>
                    <td>{{ $file->created_at?->translatedFormat('d M Y H:i') }}</td>
                    <td class="text-end"><x-delete-button :action="route('dashboard.files.destroy', $file)" label="" /></td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted">Belum ada dokumen.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

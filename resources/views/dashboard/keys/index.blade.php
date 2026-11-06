@extends('dashboard.layouts.app')

@section('title', 'Kunci Publik PGP')

@section('content')
<p class="text-muted small">Kunci terbaru dipakai di footer, halaman Kontak, dan field <code>Encryption</code> pada security.txt.</p>
<a href="{{ route('dashboard.keys.create') }}" class="btn btn-primary mb-3"><i class="bi bi-upload"></i> Unggah Kunci</a>
<div class="table-responsive col-lg-9">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Nama file</th><th>Diunggah</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($keys as $key)
                <tr>
                    <td><a href="{{ asset('storage/' . $key->path) }}" target="_blank" rel="noopener">{{ $key->name }}</a> @if ($loop->first)<span class="badge badge-soft-success">aktif</span>@endif</td>
                    <td>{{ $key->created_at?->translatedFormat('d M Y H:i') }}</td>
                    <td class="text-end"><x-delete-button :action="route('dashboard.keys.destroy', $key)" label="" /></td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted">Belum ada kunci publik.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@extends('dashboard.layouts.app')

@section('title', 'Kontak & Peta')

@section('content')
<p class="text-muted small">Data kontak terbaru ditampilkan di footer, halaman Kontak, dan security.txt.</p>
<a href="{{ route('dashboard.footers.create') }}" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> Tambah Kontak</a>
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Nama</th><th>Alamat</th><th>Email</th><th>Telepon</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($footers as $footer)
                <tr>
                    <td>{{ $footer->name }} @if ($loop->first)<span class="badge badge-soft-success">aktif</span>@endif</td>
                    <td>{{ $footer->address }}</td>
                    <td>{{ $footer->email }}</td>
                    <td>{{ $footer->telephone }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('dashboard.footers.edit', $footer) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <x-delete-button :action="route('dashboard.footers.destroy', $footer)" label="" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted">Belum ada data kontak.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

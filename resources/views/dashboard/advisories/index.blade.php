@extends('dashboard.layouts.app')

@section('title', 'Peringatan Keamanan')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <a href="{{ route('dashboard.advisories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Buat Advisory</a>
    <form class="d-flex gap-2" action="{{ route('dashboard.advisories.index') }}">
        <select name="tlp" class="form-select form-select-sm" aria-label="Filter TLP">
            <option value="">Semua TLP</option>
            @foreach (\App\Enums\Tlp::cases() as $case)
                <option value="{{ $case->value }}" @selected($tlp === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
</div>
<p class="small text-muted">Hanya advisory <x-tlp :tlp="\App\Enums\Tlp::Clear" /> yang sudah diterbitkan yang tampil di situs publik.</p>
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Kode</th><th>Judul</th><th>TLP</th><th>Keparahan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($advisories as $advisory)
                <tr>
                    <td class="font-monospace text-nowrap">{{ $advisory->code }}</td>
                    <td>{{ $advisory->title }}</td>
                    <td><x-tlp :tlp="$advisory->tlp" /></td>
                    <td><x-severity :severity="$advisory->severity" /></td>
                    <td>
                        @if ($advisory->isPubliclyVisible())
                            <span class="badge badge-soft-success">Publik</span>
                        @elseif ($advisory->published)
                            <span class="badge badge-soft-warning">Terbit (terbatas)</span>
                        @else
                            <span class="badge badge-soft-secondary">Draf</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('dashboard.advisories.show', $advisory) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('dashboard.advisories.edit', $advisory) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <x-delete-button :action="route('dashboard.advisories.destroy', $advisory)" label="" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">Belum ada advisory.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $advisories->links() }}
@endsection

@extends('dashboard.layouts.app')

@section('title', 'Aduan Insiden')

@section('content')
<form class="row g-2 mb-3" action="{{ route('dashboard.tickets.index') }}">
    <div class="col-md-4">
        <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Nomor tiket, nama, email, instansi" aria-label="Cari aduan">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm" aria-label="Status">
            <option value="">Semua status</option>
            @foreach (\App\Enums\TicketStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="category" class="form-select form-select-sm" aria-label="Jenis insiden">
            <option value="">Semua jenis</option>
            @foreach (\App\Enums\IncidentCategory::cases() as $case)
                <option value="{{ $case->value }}" @selected($category === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button></div>
</form>

<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Nomor</th><th>Jenis</th><th>Pelapor</th><th>TLP</th><th>Status</th><th>Petugas</th><th>Masuk</th></tr></thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td class="font-monospace text-nowrap"><a href="{{ route('dashboard.tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a></td>
                    <td>{{ $ticket->category->label() }}</td>
                    <td>{{ $ticket->reporter_name }}<div class="small text-muted">{{ $ticket->reporter_organization }}</div></td>
                    <td><x-tlp :tlp="$ticket->tlp" /></td>
                    <td><x-status :status="$ticket->status" /></td>
                    <td>{{ $ticket->assignee?->name ?? '-' }}</td>
                    <td class="text-nowrap small">{{ $ticket->created_at->translatedFormat('d M Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted">Tidak ada aduan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $tickets->links() }}
@endsection

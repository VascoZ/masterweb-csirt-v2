@extends('dashboard.layouts.app')

@section('title', 'Audit Log')

@section('content')
<p class="small text-muted">Catatan aktivitas bersifat permanen dan tidak dapat diubah atau dihapus melalui aplikasi.</p>
<form class="row g-2 mb-3" action="{{ route('dashboard.audit-logs.index') }}">
    <div class="col-md-3">
        <select name="event" class="form-select form-select-sm" aria-label="Jenis kejadian">
            <option value="">Semua kejadian</option>
            @foreach ($events as $event)
                <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3"><input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Deskripsi atau IP" aria-label="Cari"></div>
    <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" aria-label="Dari tanggal"></div>
    <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" aria-label="Sampai tanggal"></div>
    <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button></div>
</form>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle bg-white small">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Kejadian</th><th>Keterangan</th><th>Perubahan</th><th>IP</th></tr></thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? '-' }}</td>
                    <td><span @class(['badge', 'badge-soft-danger' => str_contains($log->event, 'failed'), 'badge-soft-secondary' => ! str_contains($log->event, 'failed')])>{{ $log->event }}</span></td>
                    <td>{{ $log->description }}</td>
                    <td>
                        @if ($log->old_values || $log->new_values)
                            <details>
                                <summary>detail</summary>
                                @if ($log->old_values)<div class="audit-values text-danger">- {{ json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</div>@endif
                                @if ($log->new_values)<div class="audit-values text-success">+ {{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</div>@endif
                            </details>
                        @endif
                    </td>
                    <td class="text-nowrap">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">Belum ada catatan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $logs->links() }}
@endsection

@extends('dashboard.layouts.app')

@section('title', 'Ringkasan')

@section('content')
@php($me = auth()->user())
<div class="welcome-card mb-4">
    <h2>Selamat datang, {{ $me->name }}</h2>
    <div class="small">
        @if ($me->last_login_at)
            Login terakhir {{ $me->last_login_at->translatedFormat('d F Y, H:i') }} dari {{ $me->last_login_ip }}.
        @else
            Ini login pertama Anda.
        @endif
    </div>
</div>

@if ($isAdmin)
    @php($icons = ['new' => ['inbox', 'primary'], 'verified' => ['patch-check', 'info'], 'in_progress' => ['hourglass-split', 'warning'], 'resolved' => ['check2-circle', 'success'], 'rejected' => ['x-circle', 'secondary']])
    <div class="row g-3 mb-4">
        @foreach ($ticketStats as $key => $stat)
            <div class="col-6 col-md-4 col-xl">
                <a href="{{ route('dashboard.tickets.index', ['status' => $key]) }}" class="stat-card">
                    <span class="icon-tile badge-soft-{{ $icons[$key][1] }}"><i class="bi bi-{{ $icons[$key][0] }}"></i></span>
                    <div>
                        <div class="label">{{ $stat['status']->label() }}</div>
                        <div class="value">{{ $stat['total'] }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Aduan yang masih terbuka</span>
                    <a href="{{ route('dashboard.tickets.index') }}" class="small fw-semibold">Lihat semua <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($openTickets as $ticket)
                        <a href="{{ route('dashboard.tickets.show', $ticket) }}" class="list-group-item list-group-item-action px-4 py-3">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span class="fw-semibold font-monospace">{{ $ticket->ticket_number }}</span>
                                <x-status :status="$ticket->status" />
                            </div>
                            <small class="text-secondary">{{ $ticket->category->label() }} · {{ $ticket->reporter_organization ?: $ticket->reporter_name }} · {{ $ticket->created_at->diffForHumans() }}</small>
                        </a>
                    @empty
                        <div class="empty-state"><i class="bi bi-inbox"></i>Tidak ada aduan terbuka.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-4 d-flex flex-column gap-3">
            <div class="stat-card">
                <span class="icon-tile badge-soft-warning"><i class="bi bi-exclamation-octagon"></i></span>
                <div class="flex-grow-1">
                    <div class="label">Peringatan keamanan</div>
                    <div class="value">{{ $advisoryCount }}</div>
                </div>
                <a href="{{ route('dashboard.advisories.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Buat</a>
            </div>
            <div class="stat-card">
                <span class="icon-tile badge-soft-primary"><i class="bi bi-newspaper"></i></span>
                <div class="flex-grow-1">
                    <div class="label">Artikel</div>
                    <div class="value">{{ $postCount }}</div>
                </div>
                <a href="{{ route('dashboard.posts.create') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg"></i> Tulis</a>
            </div>
        </div>
    </div>
@else
    <div class="row g-3">
        <div class="col-md-6 col-xl-4">
            <div class="stat-card">
                <span class="icon-tile badge-soft-primary"><i class="bi bi-newspaper"></i></span>
                <div class="flex-grow-1">
                    <div class="label">Artikel Anda</div>
                    <div class="value">{{ $postCount }}</div>
                </div>
                <a href="{{ route('dashboard.posts.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Tulis artikel</a>
            </div>
        </div>
    </div>
@endif
@endsection

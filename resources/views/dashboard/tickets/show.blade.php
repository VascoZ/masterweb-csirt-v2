@extends('dashboard.layouts.app')

@section('title', 'Aduan ' . $ticket->ticket_number)

@section('content')
<a href="{{ route('dashboard.tickets.index') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Daftar aduan</a>

<div class="alert alert-dark py-2"><x-tlp :tlp="$ticket->tlp" /> <small>{{ $ticket->tlp->description() }}</small></div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span class="fw-bold">{{ $ticket->category->label() }}</span>
                <x-status :status="$ticket->status" />
            </div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-sm-4">Pelapor</dt><dd class="col-sm-8">{{ $ticket->reporter_name }}</dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><a href="mailto:{{ $ticket->reporter_email }}">{{ $ticket->reporter_email }}</a></dd>
                    <dt class="col-sm-4">Telepon</dt><dd class="col-sm-8">{{ $ticket->reporter_phone ?: '-' }}</dd>
                    <dt class="col-sm-4">Instansi</dt><dd class="col-sm-8">{{ $ticket->reporter_organization ?: '-' }}</dd>
                    <dt class="col-sm-4">Waktu kejadian</dt><dd class="col-sm-8">{{ $ticket->incident_at?->translatedFormat('d F Y H:i') ?? '-' }}</dd>
                    <dt class="col-sm-4">Aset terdampak</dt><dd class="col-sm-8 text-break">{{ $ticket->affected_asset ?: '-' }}</dd>
                    <dt class="col-sm-4">Dilaporkan</dt><dd class="col-sm-8">{{ $ticket->created_at->translatedFormat('d F Y H:i') }} dari IP {{ $ticket->reporter_ip }}</dd>
                </dl>
                <hr>
                <h2 class="h6">Kronologi</h2>
                {{-- Isi aduan selalu di-escape (teks biasa), tidak pernah dirender sebagai HTML. --}}
                <div class="pre-line text-break">{{ $ticket->description }}</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header fw-bold">Lampiran ({{ $ticket->attachments->count() }})</div>
            <ul class="list-group list-group-flush">
                @forelse ($ticket->attachments as $attachment)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div class="text-break">
                            <i class="bi bi-paperclip"></i> {{ $attachment->original_name }}
                            <div class="small text-muted">{{ \Illuminate\Support\Number::fileSize($attachment->size, 1) }} · SHA-256 <code class="user-select-all">{{ $attachment->sha256 }}</code></div>
                        </div>
                        <a href="{{ route('dashboard.tickets.attachments.download', [$ticket, $attachment]) }}" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-download"></i> Unduh</a>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Tidak ada lampiran.</li>
                @endforelse
            </ul>
            @if ($ticket->attachments->isNotEmpty())
                <div class="card-footer small text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> Lampiran berasal dari pihak luar. Buka hanya di lingkungan analisis yang terisolasi.</div>
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <form method="post" action="{{ route('dashboard.tickets.update', $ticket) }}" class="card mb-3">
            @csrf
            @method('put')
            <div class="card-header fw-bold">Tindak Lanjut</div>
            <div class="card-body">
                <x-form.select name="status" label="Status" :value="$ticket->status"
                    :options="collect(\App\Enums\TicketStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />
                <x-form.select name="assigned_to" label="Petugas" :value="$ticket->assigned_to" placeholder="- Belum ditugaskan -"
                    :options="$staff->pluck('name', 'id')" />
                <x-form.textarea name="note" label="Catatan" rows="3" />
                <x-form.checkbox name="is_public" label="Tampilkan catatan ke pelapor (cek status)" />
                <button class="btn btn-primary">Simpan</button>
            </div>
        </form>

        <div class="card">
            <div class="card-header fw-bold">Riwayat</div>
            <ul class="list-group list-group-flush">
                @foreach ($ticket->updates as $update)
                    <li class="list-group-item small">
                        <div class="d-flex justify-content-between text-muted">
                            <span>{{ $update->user->name }} · {{ $update->created_at->translatedFormat('d M Y H:i') }}</span>
                            <span>
                                @if ($update->is_public)<i class="bi bi-eye" title="Terlihat oleh pelapor"></i>@endif
                                @if ($update->status)<x-status :status="$update->status" />@endif
                            </span>
                        </div>
                        @if ($update->note)<div class="pre-line mt-1">{{ $update->note }}</div>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection

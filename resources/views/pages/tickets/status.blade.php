@extends('layouts.app')

@section('title', 'Cek Status Aduan')
@section('heading', 'Cek Status Aduan')
@section('lead', 'Masukkan nomor tiket dan email yang Anda gunakan saat melapor.')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <x-flash />

            <form action="{{ route('tickets.status') }}" method="post" class="card mb-4">
                @csrf
                <div class="card-body p-4">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <x-form.input name="ticket_number" label="Nomor tiket" required :value="isset($ticket) ? $ticket->ticket_number : null" placeholder="{{ config('csirt.ticket_prefix') }}-YYYYMMDD-XXXXXX" class="text-uppercase font-monospace" />
                        </div>
                        <div class="col-md-5">
                            <x-form.input name="email" type="email" label="Email pelapor" required :value="isset($ticket) ? $ticket->reporter_email : null" />
                        </div>
                        <div class="col-md-2 d-grid mb-3"><button class="btn btn-primary">Cek</button></div>
                    </div>
                </div>
            </form>

            @isset($ticket)
                <div class="card">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <div class="small text-secondary">Nomor tiket</div>
                                <div class="fw-bold font-monospace fs-5">{{ $ticket->ticket_number }}</div>
                            </div>
                            <div class="text-md-end">
                                <div class="small text-secondary mb-1">Status saat ini</div>
                                <x-status :status="$ticket->status" />
                            </div>
                        </div>
                        <dl class="row small detail-list mb-4">
                            <dt class="col-sm-4">Jenis insiden</dt><dd class="col-sm-8">{{ $ticket->category->label() }}</dd>
                            <dt class="col-sm-4">Dilaporkan</dt><dd class="col-sm-8 mb-0">{{ $ticket->created_at->translatedFormat('d F Y H:i') }}</dd>
                        </dl>
                        <h2 class="h6 mb-3">Riwayat penanganan</h2>
                        <ul class="timeline">
                            @foreach ($ticket->publicUpdates as $update)
                                <li>
                                    <div class="d-flex flex-wrap gap-2 align-items-center small text-secondary mb-1">
                                        <span>{{ $update->created_at->translatedFormat('d M Y H:i') }}</span>
                                        @if ($update->status)<x-status :status="$update->status" />@endif
                                    </div>
                                    @if ($update->note)<div class="pre-line">{{ $update->note }}</div>@endif
                                </li>
                            @endforeach
                        </ul>

                        @unless ($ticket->status->isClosed())
                            <hr class="my-4">
                            <form action="{{ route('tickets.status.reply') }}" method="post">
                                @csrf
                                <input type="hidden" name="ticket_number" value="{{ $ticket->ticket_number }}">
                                <input type="hidden" name="email" value="{{ $ticket->reporter_email }}">
                                <x-form.textarea name="note" label="Tambahkan Keterangan / Balasan" rows="3" required placeholder="Tuliskan informasi tambahan terkait insiden ini..." />
                                <button class="btn btn-outline-primary btn-sm"><i class="bi bi-send"></i> Kirim Keterangan</button>
                            </form>
                        @endunless
                    </div>
                </div>
            @endisset
        </div>
    </div>
</div>
@endsection

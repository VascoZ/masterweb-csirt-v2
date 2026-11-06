@extends('layouts.app')

@section('title', 'Panduan')
@section('heading', 'Panduan Penanganan Insiden')
@section('lead', 'Dokumen panduan yang dapat diunduh untuk membantu instansi menangani insiden siber.')

@section('content')
<div class="container">
    @if ($guidances->count())
        <div class="row g-3 mb-4">
            @foreach ($guidances as $guidance)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ asset('storage/' . $guidance->path) }}" target="_blank" rel="noopener" class="quick-card">
                        <span class="icon-tile badge-soft-danger"><i class="bi bi-file-earmark-pdf"></i></span>
                        <div class="min-w-0">
                            <h3 class="text-break">{{ $guidance->name }}</h3>
                            <p>PDF · {{ \Illuminate\Support\Number::fileSize($guidance->size, 1) }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
        {{ $guidances->links() }}
    @else
        <div class="empty-state"><i class="bi bi-journal-text"></i>Belum ada panduan.</div>
    @endif
</div>
@endsection

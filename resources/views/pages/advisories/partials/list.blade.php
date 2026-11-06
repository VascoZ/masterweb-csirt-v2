@forelse ($advisories as $advisory)
    <a href="{{ route('advisories.show', $advisory) }}" class="advisory-item {{ $advisory->severity->accentClass() }}">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
            <x-severity :severity="$advisory->severity" />
            <span class="meta font-monospace">{{ $advisory->code }}</span>
            @foreach ($advisory->cveList() as $cve)
                <span class="badge badge-soft-secondary font-monospace">{{ $cve }}</span>
            @endforeach
            <span class="meta ms-auto"><i class="bi bi-calendar3"></i> {{ $advisory->published_at?->translatedFormat('d M Y') }}</span>
        </div>
        <div class="title mb-1">{{ $advisory->title }}</div>
        <div class="text-secondary small">{{ \Illuminate\Support\Str::limit($advisory->summary, 200) }}</div>
    </a>
@empty
    <div class="empty-state"><i class="bi bi-shield-check"></i>Belum ada peringatan keamanan yang dipublikasikan.</div>
@endforelse

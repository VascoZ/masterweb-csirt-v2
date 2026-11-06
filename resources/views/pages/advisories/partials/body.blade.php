{{-- Isi advisory, dipakai di halaman publik & dashboard. --}}
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <x-tlp :tlp="$advisory->tlp" />
    <x-severity :severity="$advisory->severity" />
    <span class="badge badge-soft-secondary font-monospace">{{ $advisory->code }}</span>
</div>

<h1 class="h2 mb-3 text-balance">{{ $advisory->title }}</h1>
<p class="lead text-secondary">{{ $advisory->summary }}</p>

<div class="card bg-light border-0 my-4">
    <div class="card-body">
        <dl class="row small mb-0 detail-list">
            @if ($advisory->published_at)
                <dt class="col-sm-3">Diterbitkan</dt>
                <dd class="col-sm-9">{{ $advisory->published_at->translatedFormat('d F Y H:i') }}
                    @if ($advisory->updated_at->gt($advisory->published_at->copy()->addMinute()))
                        <span class="text-secondary">· diperbarui {{ $advisory->updated_at->translatedFormat('d F Y H:i') }}</span>
                    @endif
                </dd>
            @endif
            @if ($advisory->cveList())
                <dt class="col-sm-3">CVE</dt>
                <dd class="col-sm-9">
                    @foreach ($advisory->cveList() as $cve)
                        <a href="https://www.cve.org/CVERecord?id={{ urlencode($cve) }}" target="_blank" rel="noopener noreferrer" class="badge badge-soft-primary font-monospace me-1">{{ $cve }} <i class="bi bi-box-arrow-up-right"></i></a>
                    @endforeach
                </dd>
            @endif
            @if ($advisory->affected_products)
                <dt class="col-sm-3">Produk terdampak</dt>
                <dd class="col-sm-9 pre-line mb-0">{{ $advisory->affected_products }}</dd>
            @endif
        </dl>
    </div>
</div>

<h2 class="h5 mt-4 mb-3">Deskripsi</h2>
<div class="rich-content">{!! \App\Support\HtmlSanitizer::clean($advisory->body) !!}</div>

@if ($advisory->recommendation)
    <div class="card border-success-subtle bg-success-subtle mt-4">
        <div class="card-body">
            <h2 class="h5 text-success-emphasis"><i class="bi bi-shield-check me-1"></i> Rekomendasi</h2>
            <div class="rich-content text-success-emphasis">{!! \App\Support\HtmlSanitizer::clean($advisory->recommendation) !!}</div>
        </div>
    </div>
@endif

@if ($advisory->referenceList())
    <h2 class="h5 mt-4 mb-2">Referensi</h2>
    <ul class="small">
        @foreach ($advisory->referenceList() as $url)
            <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-break">{{ $url }}</a></li>
        @endforeach
    </ul>
@endif

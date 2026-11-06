<footer class="site-footer">
    <div class="container py-5">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4">
                <div class="footer-brand mb-2">{{ \App\Support\SiteData::name() }}</div>
                <p class="mb-3">Tim Tanggap Insiden Siber — menerima, menganalisis, dan menanggapi insiden keamanan siber.</p>
                @if ($footer = $site['footer'])
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2 d-flex gap-2"><i class="bi bi-geo-alt"></i><span>{{ $footer->address }}</span></li>
                        <li class="mb-2 d-flex gap-2"><i class="bi bi-envelope"></i><a href="mailto:{{ $footer->email }}">{{ $footer->email }}</a></li>
                        <li class="mb-2 d-flex gap-2"><i class="bi bi-telephone"></i><span>{{ $footer->telephone }}</span></li>
                        @if ($site['pgpKey'])
                            <li class="d-flex gap-2"><i class="bi bi-key"></i><a href="{{ asset('storage/' . $site['pgpKey']->path) }}">Kunci publik PGP</a></li>
                        @endif
                    </ul>
                @endif
            </div>
            <div class="col-6 col-lg-2 footer-links">
                <h5>Layanan</h5>
                <a href="{{ route('tickets.create') }}">Lapor Insiden</a>
                <a href="{{ route('tickets.status.form') }}">Cek Status Aduan</a>
                <a href="{{ route('advisories.index') }}">Peringatan Keamanan</a>
                <a href="{{ route('guidances') }}">Panduan</a>
                <a href="{{ route('rfc2350') }}">RFC 2350</a>
            </div>
            <div class="col-6 col-lg-3 footer-links">
                <h5>Artikel Terbaru</h5>
                @forelse ($site['latestPosts'] as $post)
                    <a href="{{ route('posts.show', $post) }}" class="text-truncate">{{ $post->title }}</a>
                @empty
                    <span>Belum ada artikel.</span>
                @endforelse
            </div>
            <div class="col-lg-3 map">
                <h5>Lokasi</h5>
                @if ($site['footer'])
                    {!! \App\Support\HtmlSanitizer::mapsEmbed($site['footer']->maps) !!}
                @endif
            </div>
        </div>
    </div>
    <div class="footer-bottom py-3">
        <div class="container d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; {{ now()->year }} {{ \App\Support\SiteData::name() }}. Seluruh hak dilindungi.</span>
            <span class="d-flex gap-3">
                <a href="{{ route('contact') }}">Kontak</a>
                <a href="{{ route('security-txt') }}">security.txt</a>
            </span>
        </div>
    </div>
</footer>

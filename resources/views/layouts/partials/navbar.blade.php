<nav class="navbar navbar-expand-lg fixed-top site-navbar">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            @if ($site['logo'])
                <img src="{{ asset('storage/' . $site['logo']->image) }}" alt="Logo {{ \App\Support\SiteData::name() }}">
            @else
                <span class="brand-mark"><i class="bi bi-shield-lock-fill"></i></span>
                <span>{{ \App\Support\SiteData::name() }}</span>
            @endif
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Buka menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav mx-auto gap-lg-1 py-3 py-lg-0">
                @foreach ([
                    'home' => 'Beranda',
                    'profile' => 'Profil',
                    'services' => 'Layanan',
                    'advisories.index' => 'Peringatan',
                    'posts.index' => 'Artikel',
                    'guidances' => 'Panduan',
                    'rfc2350' => 'RFC 2350',
                    'contact' => 'Kontak',
                ] as $route => $label)
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs($route, str_replace('.index', '', $route) . '.*')]) href="{{ route($route) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
            <div class="d-flex gap-2 pb-3 pb-lg-0">
                <a class="btn btn-sm btn-outline-secondary d-none d-xl-inline-flex align-items-center gap-1" href="{{ route('tickets.status.form') }}"><i class="bi bi-search"></i> Cek Aduan</a>
                <a class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1" href="{{ route('tickets.create') }}"><i class="bi bi-megaphone-fill"></i> Lapor Insiden</a>
            </div>
        </div>
    </div>
</nav>

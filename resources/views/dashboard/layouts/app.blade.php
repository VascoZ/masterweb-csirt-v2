<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@hasSection('title')@yield('title') · @endif Dashboard {{ \App\Support\SiteData::name() }}</title>
    @if ($site['logo'])
        <link rel="icon" href="{{ asset('storage/' . $site['logo']->image) }}">
    @endif
    @vite(['resources/scss/dashboard.scss', 'resources/js/dashboard.js'])
</head>
<body>
@php
    $user = auth()->user();
    $menu = [
        'Umum' => [
            ['dashboard.index', 'grid-1x2', 'Ringkasan', true],
            ['dashboard.posts.index', 'newspaper', 'Artikel', true],
        ],
        'Penanganan Insiden' => [
            ['dashboard.tickets.index', 'inboxes', 'Aduan Insiden', $user->isAdmin()],
            ['dashboard.advisories.index', 'exclamation-octagon', 'Peringatan Keamanan', $user->isAdmin()],
        ],
        'Konten Situs' => [
            ['dashboard.profils.index', 'building', 'Profil', $user->isAdmin()],
            ['dashboard.services.index', 'briefcase', 'Layanan', $user->isAdmin()],
            ['dashboard.categories.index', 'tags', 'Kategori Artikel', $user->isAdmin()],
            ['dashboard.guidances.index', 'journal-text', 'Panduan', $user->isAdmin()],
            ['dashboard.files.index', 'file-earmark-pdf', 'RFC 2350', $user->isAdmin()],
            ['dashboard.keys.index', 'key', 'Kunci PGP', $user->isAdmin()],
            ['dashboard.footers.index', 'geo-alt', 'Kontak & Peta', $user->isAdmin()],
            ['dashboard.properties.index', 'image', 'Logo & Banner', $user->isAdmin()],
        ],
        'Administrasi' => [
            ['dashboard.users.index', 'people', 'Pengguna', $user->isSuperadmin()],
            ['dashboard.audit-logs.index', 'journal-check', 'Audit Log', $user->isSuperadmin()],
        ],
    ];
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

<div class="app-shell">
    <aside class="sidebar" aria-label="Menu dashboard">
        <div class="d-flex align-items-center justify-content-between">
            <a href="{{ route('dashboard.index') }}" class="brand">
                @if ($site['logo'])
                    <img src="{{ asset('storage/' . $site['logo']->image) }}" alt="">
                @else
                    <span class="brand-mark"><i class="bi bi-shield-lock-fill"></i></span>
                @endif
                <span class="lh-sm">{{ \App\Support\SiteData::name() }}<small>Dashboard</small></span>
            </a>
            <button class="btn btn-sm text-white d-lg-none me-3" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Buka menu">
                <i class="bi bi-list fs-4"></i>
            </button>
        </div>

        <div class="sidebar-collapse collapse d-lg-flex flex-column flex-grow-1" id="sidebarMenu">
            @foreach ($menu as $heading => $items)
                @php($visible = array_filter($items, fn ($item) => $item[3]))
                @if ($visible)
                    <div class="sidebar-heading">{{ $heading }}</div>
                    <ul class="nav flex-column">
                        @foreach ($visible as [$route, $icon, $label])
                            <li class="nav-item">
                                <a @class(['nav-link', 'active' => request()->routeIs($route === 'dashboard.index' ? $route : str_replace('.index', '.*', $route))]) href="{{ route($route) }}">
                                    <i class="bi bi-{{ $icon }}"></i> {{ $label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach

            <div class="sidebar-footer">
                <a href="{{ route('home') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i> Lihat situs publik</a>
            </div>
        </div>
    </aside>

    <div class="app-main">
        <header class="topbar">
            <h1 class="text-truncate">@yield('title', 'Ringkasan')</h1>
            <div class="dropdown">
                <button class="user-button dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar">{{ $initials }}</span>
                    <span class="d-none d-sm-inline">{{ $user->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-2">
                    <li class="px-3 py-2">
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="small text-secondary">{{ $user->email }} · {{ $user->roleLabel() }}</div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item rounded" href="{{ route('dashboard.security.edit') }}"><i class="bi bi-shield-lock me-2"></i> Keamanan Akun</a></li>
                    <li>
                        <form action="{{ route('logout') }}" method="post">
                            @csrf
                            <button class="dropdown-item rounded text-danger"><i class="bi bi-box-arrow-right me-2"></i> Keluar</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="app-content">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>

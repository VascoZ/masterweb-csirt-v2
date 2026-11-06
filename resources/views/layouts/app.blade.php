<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags($site['profile']?->content ?? ''))), 155))">
    <meta name="theme-color" content="#070f1f">
    <title>@hasSection('title')@yield('title') · @endif{{ \App\Support\SiteData::name() }}</title>
    @if ($site['logo'])
        <link rel="icon" href="{{ asset('storage/' . $site['logo']->image) }}">
    @endif
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @if ($site['banner'])
        <style @cspNonce>.hero { background-image: url('{{ asset('storage/' . $site['banner']->image) }}'); }</style>
    @endif
</head>
<body>
    @include('layouts.partials.navbar')

    <main>
        @hasSection('heading')
            <header class="page-hero">
                <div class="container">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
                            <li class="breadcrumb-item active" aria-current="page">@hasSection('breadcrumb') @yield('breadcrumb') @else @yield('title') @endif</li>
                        </ol>
                    </nav>
                    <h1 class="text-balance">@yield('heading')</h1>
                    @hasSection('lead')<p class="lead">@yield('lead')</p>@endif
                </div>
            </header>
            <div class="page-body">
                @yield('content')
            </div>
        @else
            @yield('content')
        @endif
    </main>

    @include('layouts.partials.footer')
</body>
</html>

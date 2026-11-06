@extends('layouts.app')

@section('title', 'Kontak')
@section('heading', 'Hubungi Kami')
@section('lead', 'Untuk melaporkan insiden, gunakan form aduan agar laporan tercatat dan mendapat nomor tiket.')

@section('content')
<div class="container">
    @if ($footer = $site['footer'])
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-4">{{ $footer->name }}</h2>
                        <ul class="info-list">
                            <li><span class="icon-tile"><i class="bi bi-geo-alt"></i></span><div><small>Alamat</small>{{ $footer->address }}</div></li>
                            <li><span class="icon-tile"><i class="bi bi-envelope"></i></span><div><small>Email</small><a href="mailto:{{ $footer->email }}">{{ $footer->email }}</a></div></li>
                            <li><span class="icon-tile"><i class="bi bi-telephone"></i></span><div><small>Telepon</small>{{ $footer->telephone }}</div></li>
                            @if ($site['pgpKey'])
                                <li><span class="icon-tile"><i class="bi bi-key"></i></span><div><small>Email terenkripsi</small><a href="{{ asset('storage/' . $site['pgpKey']->path) }}">Unduh kunci publik PGP</a></div></li>
                            @endif
                        </ul>
                        <a href="{{ route('tickets.create') }}" class="btn btn-danger w-100 mt-4"><i class="bi bi-megaphone-fill me-1"></i> Lapor Insiden</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="map-lg h-100">{!! \App\Support\HtmlSanitizer::mapsEmbed($footer->maps) !!}</div>
            </div>
        </div>
    @else
        <div class="empty-state"><i class="bi bi-geo-alt"></i>Informasi kontak belum diisi.</div>
    @endif
</div>
@endsection

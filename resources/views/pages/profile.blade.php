@extends('layouts.app')

@section('title', 'Profil')
@section('heading', 'Profil ' . \App\Support\SiteData::name())
@section('lead', 'Tentang tim, tugas, dan wilayah layanan kami.')

@section('content')
<div class="container">
    <div class="prose-column">
        @if ($site['profile'])
            <div class="rich-content">{!! \App\Support\HtmlSanitizer::clean($site['profile']->content) !!}</div>
        @else
            <div class="empty-state"><i class="bi bi-building"></i>Profil belum diisi.</div>
        @endif
    </div>
</div>
@endsection

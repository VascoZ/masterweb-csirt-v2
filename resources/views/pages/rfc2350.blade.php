@extends('layouts.app')

@section('title', 'RFC 2350')
@section('heading', 'RFC 2350')
@section('lead', 'Dokumen deskripsi layanan ' . \App\Support\SiteData::name() . ' sesuai standar RFC 2350: kontak, cakupan, kebijakan, dan layanan tim.')

@section('content')
<div class="container">
    @if ($file = $site['rfc2350'])
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center gap-2 text-secondary small"><i class="bi bi-file-earmark-pdf fs-5 text-danger"></i> {{ $file->name }}</div>
            <a href="{{ asset('storage/' . $file->path) }}" class="btn btn-primary btn-sm" download><i class="bi bi-download me-1"></i> Unduh PDF</a>
        </div>
        <iframe src="{{ asset('storage/' . $file->path) }}" class="pdf-frame" title="Dokumen RFC 2350"></iframe>
    @else
        <div class="empty-state"><i class="bi bi-file-earmark-pdf"></i>Dokumen RFC 2350 belum diunggah.</div>
    @endif
</div>
@endsection

@extends('dashboard.layouts.app')

@section('title', 'Logo & Banner')

@section('content')
<p class="text-muted small">Gambar terbaru untuk setiap jenis (Logo/Banner) yang dipakai di situs.</p>
<a href="{{ route('dashboard.properties.create') }}" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> Tambah Gambar</a>
<div class="row g-3">
    @forelse ($properties as $property)
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100">
                <img src="{{ asset('storage/' . $property->image) }}" alt="{{ $property->name }}" class="card-img-top object-fit-contain bg-light p-2" height="160">
                <div class="card-body">
                    <span class="badge badge-soft-primary">{{ $property->property }}</span>
                    <div class="fw-bold mt-1">{{ $property->name }}</div>
                </div>
                <div class="card-footer bg-white text-end">
                    <a href="{{ route('dashboard.properties.edit', $property) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <x-delete-button :action="route('dashboard.properties.destroy', $property)" label="" />
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted">Belum ada gambar.</p>
    @endforelse
</div>
@endsection

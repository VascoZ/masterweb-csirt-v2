@extends('dashboard.layouts.app')

@section('title', 'Layanan')

@section('content')
@include('dashboard.partials.rich-index', ['resource' => 'services', 'label' => 'Layanan', 'note' => 'Semua layanan ditampilkan berurutan di halaman Layanan.'])
@endsection

@extends('dashboard.layouts.app')

@section('title', $item->exists ? 'Ubah Layanan' : 'Tambah Layanan')

@section('content')
@include('dashboard.partials.rich-form', ['resource' => 'services'])
@endsection

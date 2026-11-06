@extends('dashboard.layouts.app')

@section('title', 'Profil')

@section('content')
@include('dashboard.partials.rich-index', ['resource' => 'profils', 'label' => 'Profil', 'note' => 'Profil terbaru ditampilkan di beranda dan halaman Profil.'])
@endsection

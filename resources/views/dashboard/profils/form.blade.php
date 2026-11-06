@extends('dashboard.layouts.app')

@section('title', $item->exists ? 'Ubah Profil' : 'Tambah Profil')

@section('content')
@include('dashboard.partials.rich-form', ['resource' => 'profils'])
@endsection

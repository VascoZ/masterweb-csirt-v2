@extends('dashboard.layouts.app')

@section('title', 'Profil')

@section('content')
@include('dashboard.partials.rich-show', ['resource' => 'profils'])
@endsection

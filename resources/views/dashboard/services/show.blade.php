@extends('dashboard.layouts.app')

@section('title', 'Layanan')

@section('content')
@include('dashboard.partials.rich-show', ['resource' => 'services'])
@endsection

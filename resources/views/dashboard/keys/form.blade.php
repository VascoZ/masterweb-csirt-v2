@extends('dashboard.layouts.app')

@section('title', 'Unggah Kunci Publik PGP')

@section('content')
<form method="post" action="{{ route('dashboard.keys.store') }}" enctype="multipart/form-data" class="col-lg-6">
    @csrf
    <x-form.input name="file" type="file" label="File kunci publik (.asc)" accept=".asc,.txt,.pgp,.gpg" required
        help="Ekspor dengan: gpg --armor --export email@instansi.go.id > public.asc" />
    <button class="btn btn-primary">Unggah</button>
    <a href="{{ route('dashboard.keys.index') }}" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection

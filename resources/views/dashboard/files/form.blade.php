@extends('dashboard.layouts.app')

@section('title', 'Unggah Dokumen RFC 2350')

@section('content')
<form method="post" action="{{ route('dashboard.files.store') }}" enctype="multipart/form-data" class="col-lg-6">
    @csrf
    <x-form.input name="file" type="file" label="File PDF" accept="application/pdf" required help="PDF, maks. 10 MB." />
    <button class="btn btn-primary">Unggah</button>
    <a href="{{ route('dashboard.files.index') }}" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection

@extends('dashboard.layouts.app')

@section('title', $guidance->exists ? 'Ubah Panduan' : 'Unggah Panduan')

@section('content')
<form method="post" action="{{ $guidance->exists ? route('dashboard.guidances.update', $guidance) : route('dashboard.guidances.store') }}" enctype="multipart/form-data" class="col-lg-6">
    @csrf
    @if ($guidance->exists) @method('put') @endif
    <x-form.input name="name" label="Judul panduan" :value="$guidance->name" required autofocus />
    <x-form.input name="file" type="file" label="File PDF" accept="application/pdf" :required="! $guidance->exists"
        :help="$guidance->exists ? 'Kosongkan bila tidak mengganti file (saat ini: ' . $guidance->file_name . ').' : 'PDF, maks. 10 MB.'" />
    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route('dashboard.guidances.index') }}" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection

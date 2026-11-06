@extends('dashboard.layouts.app')

@section('title', $footer->exists ? 'Ubah Kontak' : 'Tambah Kontak')

@section('content')
<form method="post" action="{{ $footer->exists ? route('dashboard.footers.update', $footer) : route('dashboard.footers.store') }}" class="col-lg-8">
    @csrf
    @if ($footer->exists) @method('put') @endif
    <x-form.input name="name" label="Nama tim / instansi" :value="$footer->name" required autofocus />
    <x-form.textarea name="address" label="Alamat" :value="$footer->address" rows="2" required />
    <div class="row">
        <div class="col-md-6"><x-form.input name="email" type="email" label="Email" :value="$footer->email" required /></div>
        <div class="col-md-6"><x-form.input name="telephone" label="Telepon" :value="$footer->telephone" required /></div>
    </div>
    <x-form.textarea name="maps" label="Embed Google Maps" :value="$footer->maps" rows="4" required
        help="Di Google Maps: Bagikan → Sematkan peta → salin HTML. Hanya URL https://www.google.com/maps/embed?... yang disimpan." />
    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route('dashboard.footers.index') }}" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection

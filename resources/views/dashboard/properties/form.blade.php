@extends('dashboard.layouts.app')

@section('title', $property->exists ? 'Ubah Gambar' : 'Tambah Gambar')

@section('content')
<form method="post" action="{{ $property->exists ? route('dashboard.properties.update', $property) : route('dashboard.properties.store') }}" enctype="multipart/form-data" class="col-lg-6">
    @csrf
    @if ($property->exists) @method('put') @endif
    <x-form.select name="property" label="Jenis" :value="$property->property" placeholder="Pilih jenis" required
        :options="array_combine($types, $types)" />
    <x-form.input name="name" label="Nama" :value="$property->name" required />
    <div class="mb-3">
        <label for="image" class="form-label">Gambar</label>
        <img @if ($property->image) src="{{ asset('storage/' . $property->image) }}" @endif alt="" id="image-preview" @class(['img-preview img-fluid d-block mb-2 rounded', 'd-none' => ! $property->image])>
        <input type="file" name="image" id="image" accept="image/png,image/jpeg,image/gif,image/webp" data-image-preview="#image-preview"
            @class(['form-control', 'is-invalid' => $errors->has('image')]) @required(! $property->exists)>
        <div class="form-text">JPG/PNG/GIF/WEBP, maks. 2 MB. SVG tidak diizinkan.</div>
        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route('dashboard.properties.index') }}" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection

@extends('dashboard.layouts.app')

@section('title', $advisory->exists ? 'Ubah Advisory ' . $advisory->code : 'Buat Advisory')

@section('content')
<form method="post" action="{{ $advisory->exists ? route('dashboard.advisories.update', $advisory) : route('dashboard.advisories.store') }}" class="col-xl-10">
    @csrf
    @if ($advisory->exists) @method('put') @endif

    <x-form.input name="title" label="Judul" :value="$advisory->title" required autofocus
        data-slug-url="{{ route('dashboard.advisories.checkSlug') }}" data-slug-target="#slug" />
    <x-form.input name="slug" label="Slug (URL)" :value="$advisory->slug" required />

    <div class="row">
        <div class="col-md-4">
            <x-form.select name="tlp" label="Klasifikasi TLP" :value="$advisory->tlp" required
                :options="collect(\App\Enums\Tlp::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])"
                help="Hanya TLP:CLEAR yang tampil di situs publik." />
        </div>
        <div class="col-md-4">
            <x-form.select name="severity" label="Tingkat keparahan" :value="$advisory->severity" required
                :options="collect(\App\Enums\Severity::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])" />
        </div>
        <div class="col-md-4">
            <x-form.input name="cve" label="CVE" :value="$advisory->cve" placeholder="CVE-2026-12345, CVE-2026-23456" />
        </div>
    </div>

    <x-form.textarea name="summary" label="Ringkasan" :value="$advisory->summary" rows="2" required maxlength="1000" />
    <x-form.textarea name="affected_products" label="Produk / versi terdampak" :value="$advisory->affected_products" rows="3" help="Satu produk per baris." />
    <x-form.trix name="body" label="Deskripsi" :value="$advisory->body" />
    <x-form.trix name="recommendation" label="Rekomendasi / mitigasi" :value="$advisory->recommendation" />
    <x-form.textarea name="references" label="Referensi" :value="$advisory->references" rows="3" help="Satu URL (https://...) per baris." />

    <x-form.checkbox name="published" label="Terbitkan" :checked="$advisory->published" />

    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route('dashboard.advisories.index') }}" class="btn btn-outline-secondary">Batal</a>
</form>
@endsection

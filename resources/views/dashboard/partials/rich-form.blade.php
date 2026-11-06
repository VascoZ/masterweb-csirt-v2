{{-- Form konten berformat (Profil / Layanan). Param: $item, $resource --}}
<form method="post" action="{{ $item->exists ? route("dashboard.{$resource}.update", $item) : route("dashboard.{$resource}.store") }}" class="col-lg-9">
    @csrf
    @if ($item->exists) @method('put') @endif
    <x-form.input name="name" label="Nama" :value="$item->name" required autofocus />
    <x-form.trix name="content" label="Isi" :value="$item->content" />
    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route("dashboard.{$resource}.index") }}" class="btn btn-outline-secondary">Batal</a>
</form>

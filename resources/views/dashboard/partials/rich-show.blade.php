{{-- Pratinjau konten berformat. Param: $item, $resource --}}
<div class="col-lg-9">
    <div class="mb-3 d-flex gap-2">
        <a href="{{ route("dashboard.{$resource}.index") }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        <a href="{{ route("dashboard.{$resource}.edit", $item) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Ubah</a>
        <x-delete-button :action="route('dashboard.' . $resource . '.destroy', $item)" class="btn btn-outline-danger" />
    </div>
    <article class="card card-body">
        <h2>{{ $item->name }}</h2>
        <div class="rich-content">{!! \App\Support\HtmlSanitizer::clean($item->content) !!}</div>
    </article>
</div>

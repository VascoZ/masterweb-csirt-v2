@props(['action', 'label' => 'Hapus', 'confirm' => 'Yakin ingin menghapus data ini?'])
<form action="{{ $action }}" method="post" class="d-inline">
    @csrf
    @method('delete')
    <button type="submit" {{ $attributes->merge(['class' => 'btn btn-sm btn-outline-danger']) }} data-confirm="{{ $confirm }}">
        <i class="bi bi-trash"></i> {{ $label }}
    </button>
</form>

{{-- Daftar konten berformat (Profil / Layanan). Param: $items, $resource, $label, $note --}}
@if (! empty($note))<p class="text-muted small">{{ $note }}</p>@endif
<a href="{{ route("dashboard.{$resource}.create") }}" class="btn btn-primary mb-3"><i class="bi bi-plus-lg"></i> Tambah {{ $label }}</a>
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>#</th><th>Nama</th><th>Diperbarui</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->updated_at?->translatedFormat('d M Y H:i') }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route("dashboard.{$resource}.show", $item) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        <a href="{{ route("dashboard.{$resource}.edit", $item) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <x-delete-button :action="route('dashboard.' . $resource . '.destroy', $item)" label="" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

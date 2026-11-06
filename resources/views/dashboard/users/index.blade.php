@extends('dashboard.layouts.app')

@section('title', 'Pengguna')

@section('content')
<a href="{{ route('dashboard.users.create') }}" class="btn btn-primary mb-3"><i class="bi bi-person-plus"></i> Tambah Pengguna</a>
<div class="table-responsive">
    <table class="table table-hover align-middle bg-white">
        <thead><tr><th>Nama</th><th>Username</th><th>Email</th><th>Peran</th><th>2FA</th><th>Login terakhir</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td><code>{{ $user->username }}</code></td>
                    <td>{{ $user->email }}</td>
                    <td><span @class(['badge', 'badge-soft-danger' => $user->isSuperadmin(), 'badge-soft-primary' => ! $user->isSuperadmin() && $user->isAdmin(), 'badge-soft-secondary' => ! $user->isAdmin()])>{{ $user->roleLabel() }}</span></td>
                    <td>
                        @if ($user->hasTwoFactorEnabled())
                            <i class="bi bi-shield-check text-success" title="Aktif"></i>
                        @else
                            <i class="bi bi-shield-exclamation text-warning" title="Belum aktif"></i>
                        @endif
                    </td>
                    <td class="small">{{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? '-' }}<div class="text-muted">{{ $user->last_login_ip }}</div></td>
                    <td class="text-end"><a href="{{ route('dashboard.users.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

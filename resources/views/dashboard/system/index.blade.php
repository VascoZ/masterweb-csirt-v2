@extends('dashboard.layouts.app')

@section('title', 'Pengaturan Sistem')

@php
    $levels = ['ok' => ['success', 'check-circle-fill'], 'warn' => ['warning', 'exclamation-triangle-fill'], 'bad' => ['danger', 'x-octagon-fill'], 'info' => ['secondary', 'info-circle-fill']];
    $groups = ['fitur' => 'Fitur', 'notifikasi' => 'Notifikasi email', 'keamanan' => 'Sesi', 'retensi' => 'Retensi data (UU PDP)', 'tugas' => 'Tugas terjadwal'];
@endphp

@section('content')
@if (session('task_output'))
    <div class="card mb-4 border-secondary-subtle">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Hasil eksekusi terakhir</span>
        </div>
        <pre class="mb-0 p-3 small bg-body-tertiary text-wrap">{{ session('task_output') }}</pre>
    </div>
@endif

<div class="row g-4">
    {{-- ============ Status sistem ============ --}}
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-heart-pulse me-1"></i> Status sistem</div>
            <ul class="list-group list-group-flush">
                @foreach ($checks as $check)
                    @php([$color, $icon] = $levels[$check['level']])
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <span class="fw-semibold">{{ $check['label'] }}</span>
                            <span class="text-{{ $color }}-emphasis text-end small"><i class="bi bi-{{ $icon }} text-{{ $color }}"></i> {{ $check['value'] }}</span>
                        </div>
                        @if ($check['hint'])
                            <div class="small text-secondary mt-1">{{ $check['hint'] }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="card-footer small text-secondary">
                Whitelist login, kewajiban 2FA, host dan proxy tepercaya sengaja <strong>hanya bisa diubah lewat <code>.env</code></strong> di server, agar kesalahan di dashboard tidak mengunci semua pengguna atau melemahkan keamanan.
            </div>
        </div>
    </div>

    <div class="col-xl-7 d-flex flex-column gap-4">
        {{-- ============ Tugas terjadwal ============ --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i> Tugas terjadwal</div>
            @foreach ($tasks as $task)
                <div class="card-body border-bottom">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <div class="fw-bold">{{ $task['label'] }}
                                <span @class(['badge ms-1', 'badge-soft-success' => $task['enabled'], 'badge-soft-secondary' => ! $task['enabled']])>{{ $task['enabled'] ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <div class="small text-secondary">{{ $task['description'] }}</div>
                            <div class="small mt-2">
                                Jadwal: setiap hari pukul <strong>{{ $task['time'] }}</strong>
                                @if ($task['next']) · berikutnya {{ $task['next']->diffForHumans() }} @endif
                                <br>Terakhir: @if ($task['last']) {{ $task['last']->started_at->translatedFormat('d M Y H:i') }} ({{ $task['last']->succeeded() ? 'berhasil' : 'GAGAL' }}) @else belum pernah @endif
                            </div>
                        </div>
                        <form method="post" action="{{ route('dashboard.system.tasks.run', $task['key']) }}" class="d-flex gap-2 align-items-start">
                            @csrf
                            <button name="dry_run" value="1" class="btn btn-sm btn-outline-secondary" title="Hitung data yang akan diproses tanpa mengubah apa pun"><i class="bi bi-eye"></i> Simulasi</button>
                            <button name="dry_run" value="0" class="btn btn-sm btn-primary"
                                data-confirm="Jalankan '{{ $task['label'] }}' sekarang? Penghapusan/anonimisasi data bersifat permanen."><i class="bi bi-play-fill"></i> Jalankan</button>
                        </form>
                    </div>
                </div>
            @endforeach
            <div class="card-body">
                <div class="fw-semibold mb-2 small text-uppercase text-secondary">Riwayat eksekusi</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead><tr><th>Waktu</th><th>Tugas</th><th>Pemicu</th><th>Hasil</th></tr></thead>
                        <tbody>
                            @forelse ($runs as $run)
                                <tr>
                                    <td class="text-nowrap">{{ $run->started_at->translatedFormat('d M H:i:s') }}</td>
                                    <td>{{ $tasks[$run->task]['label'] ?? $run->task }}@if ($run->dry_run) <span class="badge badge-soft-info">simulasi</span>@endif</td>
                                    <td>{{ $run->trigger === 'manual' ? $run->user->name : 'Jadwal' }}</td>
                                    <td><span @class(['badge', 'badge-soft-success' => $run->succeeded(), 'badge-soft-danger' => ! $run->succeeded()])>{{ $run->succeeded() ? 'Berhasil' : 'Gagal' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-secondary">Belum ada eksekusi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ============ Pengaturan fitur ============ --}}
        <form method="post" action="{{ route('dashboard.system.settings') }}" class="card">
            @csrf
            @method('put')
            <div class="card-header"><i class="bi bi-sliders me-1"></i> Pengaturan fitur</div>
            <div class="card-body">
                @foreach ($groups as $group => $title)
                    <div class="fw-semibold small text-uppercase text-secondary mb-2 {{ $loop->first ? '' : 'mt-3' }}">{{ $title }}</div>
                    @foreach ($definitions as $key => $definition)
                        @continue($definition['group'] !== $group)
                        @switch($definition['type'])
                            @case('bool')
                                <x-form.checkbox :name="$key" :label="$definition['label']" :checked="$values[$key]" :help="$definition['help']" />
                                @break
                            @case('select')
                                <x-form.select :name="$key" :label="$definition['label']" :value="$values[$key]" :options="$definition['options']" :help="$definition['help']" />
                                @break
                            @case('time')
                                <x-form.input :name="$key" type="time" :label="$definition['label']" :value="$values[$key]" :help="$definition['help']" required />
                                @break
                            @case('email')
                                <x-form.input :name="$key" type="email" :label="$definition['label']" :value="$values[$key]" :help="$definition['help']" />
                                @break
                            @default
                                <x-form.input :name="$key" type="number" min="0" :label="$definition['label']" :value="$values[$key]" :help="$definition['help']" required />
                        @endswitch
                    @endforeach
                @endforeach
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="small text-secondary">Perubahan tercatat di Audit Log.</span>
                <button class="btn btn-primary">Simpan Pengaturan</button>
            </div>
        </form>
    </div>
</div>
@endsection

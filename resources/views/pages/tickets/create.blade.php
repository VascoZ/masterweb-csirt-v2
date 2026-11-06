@extends('layouts.app')

@section('title', 'Lapor Insiden Siber')
@section('heading', 'Lapor Insiden Siber')
@section('lead', 'Laporkan insiden yang Anda alami atau temukan. Setiap aduan mendapat nomor tiket untuk memantau status penanganannya.')

@section('content')
<div class="container">
    <div class="row g-4">
        <div class="col-lg-8">
            <x-flash />

            <form action="{{ route('tickets.store') }}" method="post" enctype="multipart/form-data" class="card" novalidate>
                @csrf
                <div class="card-body p-4 p-lg-5">
                    <div class="form-section-title"><span class="step">1</span> Data pelapor</div>
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="reporter_name" label="Nama lengkap *" required maxlength="255" autocomplete="name" /></div>
                        <div class="col-md-6"><x-form.input name="reporter_email" type="email" label="Email *" required autocomplete="email" help="Tanda terima & nomor tiket dikirim ke email ini." /></div>
                        <div class="col-md-6"><x-form.input name="reporter_phone" type="tel" label="Nomor telepon" autocomplete="tel" /></div>
                        <div class="col-md-6"><x-form.input name="reporter_organization" label="Instansi / organisasi" autocomplete="organization" /></div>
                    </div>

                    <div class="form-section-title mt-3"><span class="step">2</span> Detail insiden</div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-form.select name="category" label="Jenis insiden *" placeholder="Pilih jenis insiden" required
                                :options="collect($categories)->mapWithKeys(fn ($c) => [$c->value => $c->label()])" />
                        </div>
                        <div class="col-md-6"><x-form.input name="incident_at" type="datetime-local" label="Waktu kejadian (perkiraan)" :max="now()->format('Y-m-d\TH:i')" /></div>
                    </div>
                    <x-form.input name="affected_asset" label="Aset terdampak" placeholder="Contoh: https://contoh.go.id, 103.x.x.x, nama aplikasi" />
                    <x-form.textarea name="description" label="Kronologi / deskripsi *" rows="7" required minlength="20"
                        help="Jelaskan apa yang terjadi, kapan pertama kali diketahui, dampak yang dirasakan, dan langkah yang sudah dilakukan." />
                    <x-form.select name="tlp" label="Klasifikasi informasi (TLP) *" value="AMBER"
                        :options="collect($tlps)->mapWithKeys(fn ($t) => [$t->value => $t->label() . ' — ' . $t->description()])"
                        help="Menentukan sejauh mana informasi dalam aduan ini boleh dibagikan oleh tim kami." />

                    <div class="form-section-title mt-3"><span class="step">3</span> Bukti & verifikasi</div>
                    <div class="mb-3">
                        <label for="attachments" class="form-label">Lampiran bukti</label>
                        <input type="file" name="attachments[]" id="attachments" multiple
                            accept="{{ collect(config('csirt.ticket_attachment.mimes'))->map(fn ($m) => '.' . $m)->implode(',') }}"
                            @class(['form-control', 'is-invalid' => $errors->has('attachments') || $errors->has('attachments.*')])>
                        <div class="form-text">
                            Maks. {{ config('csirt.ticket_attachment.max_files') }} file, masing-masing
                            {{ \Illuminate\Support\Number::fileSize(config('csirt.ticket_attachment.max_kb') * 1024) }}.
                            Format: {{ implode(', ', config('csirt.ticket_attachment.mimes')) }}.
                        </div>
                        @error('attachments')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @foreach ($errors->get('attachments.*') as $messages)
                            <div class="invalid-feedback">{{ $messages[0] }}</div>
                        @endforeach
                    </div>

                    <x-captcha />

                    <x-form.checkbox name="agreement" label="Saya menyatakan informasi yang disampaikan benar dan bersedia dihubungi untuk keperluan verifikasi. *" />

                    <button type="submit" class="btn btn-danger btn-lg px-4"><i class="bi bi-send-fill me-1"></i> Kirim Aduan</button>
                </div>
            </form>
        </div>

        <aside class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3"><i class="bi bi-shield-exclamation text-warning me-1"></i> Sebelum mengirim</h2>
                    <ul class="small text-secondary ps-3 mb-0">
                        <li class="mb-2">Jangan kirim password, kunci privat, atau data pribadi yang tidak diperlukan.</li>
                        <li class="mb-2">Untuk informasi sangat sensitif, enkripsi lampiran dengan
                            @if ($site['pgpKey'])
                                <a href="{{ asset('storage/' . $site['pgpKey']->path) }}">kunci publik PGP kami</a>.
                            @else
                                kunci publik PGP kami.
                            @endif
                        </li>
                        <li>Simpan bukti asli (log, tangkapan layar) dan jangan matikan sistem yang terdampak bila tidak perlu.</li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-2">Sudah pernah melapor?</h2>
                    <p class="small text-secondary">Pantau perkembangan aduan dengan nomor tiket dan email Anda.</p>
                    <a href="{{ route('tickets.status.form') }}" class="btn btn-outline-primary btn-sm w-100">Cek Status Aduan</a>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

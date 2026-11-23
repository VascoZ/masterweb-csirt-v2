# Hasil Review Keamanan Tambahan — Web CSIRT v2

| Item | Keterangan |
| --- | --- |
| Tanggal | 23 November 2026 |
| Objek | `template-csirt-v2` (Laravel 12, PHP 8.5) |
| Metode | Review kode, uji HTTP langsung pada deployment lokal, audit dependency |
| Lingkup | Autentikasi/otorisasi, input handling, upload, XSS/SQLi, 2FA, header/CSP, konfigurasi Docker/Nginx, dependency |

## Ringkasan

- **2 temuan Low terkonfirmasi** melalui uji langsung: HTTP 500 via array di body `/login`, dan enumerasi username lewat `/posts?author=`.
- **3 hardening Low/Info** ditambahkan: array pada `current_password`, filter tanggal audit log, dan sanitasi nama lampiran.
- **Audit dependency bersih**: `composer audit` → 0 advisory; `npm audit --omit=dev` → 0 vulnerability.
- Tidak ditemukan indikasi SQL injection, stored XSS, IDOR, bypass otorisasi, path traversal, SSRF, open redirect, kebocoran draft, atau kebocoran data tiket pada review ini.

## Temuan dan Perbaikan

### 1. HTTP 500 via Array pada Body Login (Low) — Diperbaiki

**Reproduksi:** `POST /login` dengan body `email[]=x&password[]=x&cf-turnstile-response[]=x` (dengan CSRF valid) mengembalikan **HTTP 500**.

**Penyebab:** rate limiter login menjalankan `Str::lower((string) $request->input('email'))`. Nilai array memicu *Array to string conversion* yang diubah menjadi exception oleh error handler Laravel.

**Perbaikan:** nilai email diperiksa tipenya terlebih dahulu; array dipetakan ke identitas `invalid` untuk rate limit, lalu validasi mengembalikan 302 dengan error (bukan 500).

**File:** `app/Providers/AppServiceProvider.php`
**Tes:** `test_login_with_array_body_returns_validation_not_server_error`

### 2. Enumerasi Username via Filter Penulis Publik (Low) — Diperbaiki

**Reproduksi:**
- `GET /posts?author=admin` → judul **“Semua Artikel oleh Administrator”**
- `GET /posts?author=tidakada` → judul **“Semua Artikel”**

Perbedaan ini membocorkan keberadaan username, meskipun pengguna tersebut belum punya artikel terbit.

**Perbaikan:** nama penulis hanya ditampilkan bila pengguna memiliki **artikel yang sudah dipublikasikan**. Nama penulis artikel publik memang sudah bersifat publik, sehingga tidak ada informasi baru yang bocor.

**File:** `app/Http/Controllers/PostController.php`
**Tes:** `test_public_author_filter_does_not_reveal_draft_only_users`

### 3. HTTP 500 via Array pada `current_password` (Low) — Diperbaiki

**Reproduksi:** pengguna yang sudah login mengirim `current_password[]=x` ke `/dashboard/security/password`, `/dashboard/security/two-factor/recovery-codes`, atau `DELETE /dashboard/security/two-factor`. Rule `current_password` memanggil `password_verify()` dengan array.

**Perbaikan:** validasi diberi `bail` + `string` sebelum `current_password`, sehingga input array ditolak dengan pesan validasi tanpa menjalankan hash check.

**File:** `app/Http/Controllers/Dashboard/SecurityController.php`
**Tes:** `test_security_password_rejects_array_current_password_without_server_error`

### 4. HTTP 500 via Filter Tanggal Audit Log (Info) — Diperbaiki

**Reproduksi:** `GET /dashboard/audit-logs?from=bukan-tanggal` berpotensi memicu exception saat `Request::date()` mem-parse string tidak valid.

**Perbaikan:** parsing tanggal dibungkus `rescue(..., null, false)`; filter yang tidak valid diabaikan.

**File:** `app/Http/Controllers/Dashboard/AuditLogController.php`
**Tes:** `test_audit_log_ignores_invalid_date_filter`

### 5. Nama Lampiran Mengandung Karakter Kontrol (Info) — Diperbaiki

Nama asli lampiran dapat memuat karakter kontrol (mis. CR/LF) yang membuat pembuatan header `Content-Disposition` gagal saat admin mengunduh. Nama kini dibersihkan dari karakter kontrol dan diberi fallback `lampiran`.

**File:** `app/Http/Controllers/TicketController.php`

## Rekomendasi Hardening Lanjutan

1. **Pisahkan environment container.** `docker-compose.yml` memakai `env_file: .env` untuk `app` dan `scheduler`, sehingga keduanya menerima `DB_ROOT_PASSWORD` dan `ADMIN_PASSWORD` yang tidak dibutuhkan saat runtime. Gunakan file env runtime terpisah atau daftar `environment:` minimal.
2. **Apache:** bila aplikasi dipasang di Apache, tambahkan allow-list ekstensi pada `/storage/` seperti konfigurasi Nginx, agar hanya tipe berkas yang diizinkan yang dilayani.
3. **Rate limit `checkSlug`** untuk kategori/advisory (saat ini admin-only) bila ingin mencegah enumerasi slug secara masif.
4. **Dependency & image scanning:** jalankan `composer audit`, `npm audit`, dan Trivy secara berkala/CI.
5. **Turnstile produksi:** pastikan `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY` memakai kunci widget instansi, bukan kunci uji, dan pantau `error-codes` Cloudflare.

## Kontrol yang Diverifikasi Baik

- Otorisasi per peran dan policy (posts, tickets, users, audit log, settings).
- CSRF pada seluruh form state-changing; session regeneration saat login; 2FA TOTP dengan anti-replay dan kode pemulihan sekali pakai.
- Sanitasi HTML (`symfony/html-sanitizer`), CSP berbasis nonce tanpa `unsafe-inline`, dan header keamanan.
- Upload: PDF untuk dokumen, gambar tanpa SVG, lampiran privat diunduh sebagai `application/octet-stream`.
- Pencegahan enumerasi tiket (nomor + email), draft post tidak dapat diakses publik, slug tidak membocorkan keberadaan.
- Audit log meredaksi password/secret 2FA dan data pribadi pelapor.
- Nginx: hanya `index.php` yang dieksekusi, dotfile diblokir, `/build/manifest.json` diblokir, `/storage/` dibatasi ekstensi.

## Status

Perbaikan temuan 1–5 sudah diterapkan di working tree beserta tes regresi. Hasil tes: **150 tes lulus (564 assertion)**.

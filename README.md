# Template Situs CSIRT (TTIS) — v2

Template situs web Tim Tanggap Insiden Siber (CSIRT/TTIS) untuk instansi pemerintah.
Versi ini adalah pembaruan menyeluruh dari *MasterWebCSIRT 2022* (Laravel 8).

| Komponen | Versi |
|---|---|
| PHP | 8.5 |
| Laravel | 12.x |
| Database | MySQL 8 / MariaDB 10.11+ (disarankan MariaDB 11.4 LTS) |
| Frontend | Vite 7, Bootstrap 5.3, Bootstrap Icons — semua aset lokal, tanpa CDN, tanpa jQuery |

## Fitur

**Halaman publik**
- Beranda, Profil, Layanan, Artikel, Panduan, RFC 2350, Kontak
- **Form aduan insiden** dengan nomor tiket, lampiran bukti (disimpan privat), klasifikasi TLP, dan **cek status** (nomor tiket + email)
- **Peringatan keamanan (advisory)** dengan label **TLP 2.0** dan tingkat keparahan. Hanya `TLP:CLEAR` yang tampil publik.
- **`/.well-known/security.txt`** (RFC 9116), dibangkitkan otomatis dari data kontak dan kunci PGP

**Dashboard**
- Peran: **Penulis** (draf artikel), **Admin** (konten, aduan, advisory), **Superadmin** (pengguna, audit log)
- Penanganan aduan: status, penugasan petugas, catatan internal/publik, unduh lampiran (dengan hash SHA-256)
- **2FA (TOTP)** dengan kode pemulihan, wajib secara default
- **Audit log** append-only untuk login, perubahan data, unduhan bukti, penolakan akses, dan aktivitas 2FA
- **Pengaturan Sistem** (superadmin): status kesehatan, tugas terjadwal (aktif/jam/jalankan manual/riwayat), skema durasi sesi, buka-tutup form aduan, notifikasi email, retensi data

**Keamanan**
- Content Security Policy berbasis nonce (tanpa `unsafe-inline`), header keamanan lengkap
- Sanitasi HTML (symfony/html-sanitizer) untuk semua konten editor
- Policy Laravel untuk setiap resource, rate limiting login/aduan/2FA
- Whitelist IP (mendukung CIDR) untuk halaman login
- Upload: nama file acak, validasi tipe, SVG ditolak, bukti aduan di disk privat

## Instalasi On-Premise (Ubuntu 22.04 / 24.04)

Satu perintah untuk memasang seluruh stack (Nginx, PHP-FPM 8.5, MariaDB, fail2ban, backup harian) di server yang masih kosong. Mode default **sederhana**: situs dibuka lewat alamat IP server, tanpa DNS, tanpa HTTPS, dan firewall tidak diaktifkan. DNS, HTTPS, dan firewall dipasang belakangan:

```bash
sudo apt install -y git
git clone https://github.com/VascoZ/masterweb-csirt-v2.git /tmp/csirt
sudo bash /tmp/csirt/scripts/install-ubuntu.sh
```

Selesai instalasi, buka `http://<IP-server>`. Opsi lengkap, mode non-interaktif, dan cara memasang DNS/HTTPS/firewall sesudahnya: [docs/DEPLOY.md](docs/DEPLOY.md#1-script-instalasi-otomatis-ubuntu).

## Mulai Cepat (Docker)

```bash
git clone https://github.com/VascoZ/masterweb-csirt-v2.git csirt && cd csirt
cp .env.docker.example .env
echo "APP_KEY=base64:$(openssl rand -base64 32)"   # salin ke APP_KEY di .env
openssl rand -hex 24                               # untuk DB_PASSWORD dan DB_ROOT_PASSWORD
# Isi juga APP_URL, ADMIN_EMAIL, ADMIN_PASSWORD di .env
docker compose up -d --build
```

Situs berjalan di `http://127.0.0.1:8080` dan halaman login ada di `/csirt-login`. Untuk **uji coba lokal tanpa HTTPS**, set `SESSION_SECURE_COOKIE=false` dan tambahkan `172.16.0.0/12` ke `CSIRT_LOGIN_WHITELIST` (alasannya dijelaskan di [DEPLOY.md](docs/DEPLOY.md#23-jalankan)). Untuk produksi, pasang TLS sesuai [DEPLOY.md bagian 2.4](docs/DEPLOY.md#24-pasang-tls-dengan-nginx-di-host).

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [docs/DEPLOY.md](docs/DEPLOY.md) | Script instalasi otomatis, Docker Compose, instalasi manual, pemulihan akses admin |
| [docs/HARDENING.md](docs/HARDENING.md) | Checklist hardening server, TLS, firewall, backup, catatan CVE PHP |
| [docs/KEPATUHAN-KEAMANAN.md](docs/KEPATUHAN-KEAMANAN.md) | Hasil analisis whitebox dan pemetaan ke OWASP, ISO/IEC 27001, NIST |
| [docs/MIGRASI-2022.md](docs/MIGRASI-2022.md) | Migrasi data dari MasterWebCSIRT 2022 |
| [docs/nginx-host.conf.example](docs/nginx-host.conf.example) | Contoh Nginx host dengan TLS |

## Perintah Artisan Khusus

| Perintah | Fungsi |
|---|---|
| `php artisan csirt:install` | Instalasi awal: migrasi, superadmin pertama, konten awal, dan cek konfigurasi. Aman dijalankan ulang. |
| `php artisan csirt:reset-admin <email>` | Memulihkan akses: password baru, reset 2FA, jadikan superadmin |
| `php artisan csirt:prune [--dry-run]` | Terapkan kebijakan retensi data (otomatis tiap hari lewat scheduler) |

## Pengembangan (lokal)

Butuh PHP 8.5 (ekstensi `gd`, `intl`, `pdo_sqlite`, `zip`), Composer, dan Node.js 22.

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# Ubah .env untuk lokal: APP_ENV=local, APP_DEBUG=true, APP_URL=http://127.0.0.1:8000,
# DB_CONNECTION=sqlite, SESSION_SECURE_COOKIE=false, lalu HAPUS baris DB_DATABASE
# (agar memakai database/database.sqlite)
touch database/database.sqlite
php artisan migrate --seed      # data contoh; akun demo ditampilkan di terminal
php artisan serve
php artisan test                # 138 test, memakai SQLite in-memory
```

## Lisensi

Kode dasar Laravel berlisensi MIT.

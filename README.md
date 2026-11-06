# Template Situs CSIRT (TTIS) — v2

Template situs web Tim Tanggap Insiden Siber (CSIRT/TTIS) untuk instansi pemerintah.
Versi ini adalah pembaruan menyeluruh dari *MasterWebCSIRT 2022* (Laravel 8).

| Komponen | Versi |
|---|---|
| PHP | 8.3 |
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
- **Audit log** append-only untuk login, perubahan data, unduhan bukti, dan aktivitas 2FA

**Keamanan**
- Content Security Policy berbasis nonce (tanpa `unsafe-inline`), header keamanan lengkap
- Sanitasi HTML (symfony/html-sanitizer) untuk semua konten editor
- Policy Laravel untuk setiap resource, rate limiting login/aduan/2FA
- Whitelist IP (mendukung CIDR) untuk halaman login
- Upload: nama file acak, validasi tipe, SVG ditolak, bukti aduan di disk privat

## Instalasi On-Premise (Ubuntu 22.04 / 24.04)

Satu perintah untuk memasang seluruh stack (Nginx, PHP-FPM 8.3, MariaDB, TLS, firewall, fail2ban, backup harian):

```bash
git clone https://github.com/VascoZ/masterweb-csirt-v2.git /tmp/csirt
sudo bash /tmp/csirt/scripts/install-ubuntu.sh
```

Opsi lengkap dan mode non-interaktif: [docs/DEPLOY.md](docs/DEPLOY.md#script-instalasi-otomatis-ubuntu).

## Mulai Cepat (Docker)

```bash
cp .env.docker.example .env
# Isi APP_KEY, APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD, ADMIN_EMAIL, ADMIN_PASSWORD
docker compose run --rm --no-deps app php artisan key:generate --show   # salin ke APP_KEY
docker compose up -d --build
```

Situs berjalan di `http://127.0.0.1:8080`. Login dashboard: `/csirt-login` (hanya dari IP pada `CSIRT_LOGIN_WHITELIST`).

Panduan lengkap: **[docs/DEPLOY.md](docs/DEPLOY.md)**.

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [docs/DEPLOY.md](docs/DEPLOY.md) | Deploy dengan Docker dan instalasi manual (Ubuntu + Nginx + PHP-FPM) |
| [docs/HARDENING.md](docs/HARDENING.md) | Checklist hardening server, TLS, firewall, backup |
| [docs/MIGRASI-2022.md](docs/MIGRASI-2022.md) | Migrasi data dari MasterWebCSIRT 2022 |
| [docs/nginx-host.conf.example](docs/nginx-host.conf.example) | Contoh Nginx host dengan TLS |

## Pengembangan

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed      # data contoh (hanya untuk lokal)
php artisan test
```

## Lisensi

Kode dasar Laravel berlisensi MIT.

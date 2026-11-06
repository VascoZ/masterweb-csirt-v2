# Panduan Deploy

Ada tiga cara memasang situs CSIRT:

- **Script otomatis (on-premise)**: satu perintah di server Ubuntu 22.04/24.04 yang masih kosong. Lihat bagian [berikut](#script-instalasi-otomatis-ubuntu).
- **A. Docker Compose**: semua komponen (PHP-FPM, Nginx, MariaDB) sudah dikonfigurasi dan di-hardening.
- **B. Manual**: langkah demi langkah di Ubuntu dengan Nginx, PHP-FPM 8.3, dan MariaDB.

---

## Script Instalasi Otomatis (Ubuntu)

[`scripts/install-ubuntu.sh`](../scripts/install-ubuntu.sh) memasang seluruh stack di server Ubuntu Server **22.04 atau 24.04 LTS**:

- Nginx, PHP-FPM 8.3 (pool khusus dengan `open_basedir`), MariaDB (hanya `127.0.0.1`), Composer, dan Node.js (hanya untuk build aset)
- Aplikasi di `/var/www/csirt`: kode milik user `csirt` dan read-only bagi PHP; hanya `storage/` yang bisa ditulis
- TLS: Let's Encrypt, sertifikat instansi, atau self-signed
- UFW, fail2ban (SSH dan rate limit Nginx), unattended-upgrades, chrony
- Backup harian pukul 02:15 ke `/var/backups/csirt`, serta cron scheduler Laravel

### Interaktif

```bash
git clone https://github.com/VascoZ/masterweb-csirt-v2.git /tmp/csirt
sudo bash /tmp/csirt/scripts/install-ubuntu.sh
```

Script akan menanyakan domain, nama tim, mode TLS, whitelist login, email, dan password superadmin.

### Non-interaktif

```bash
sudo DOMAIN=csirt.contoh.go.id SITE_NAME="Contoh-CSIRT" \
     TLS_MODE=letsencrypt LE_EMAIL=admin@contoh.go.id \
     ADMIN_EMAIL=admin@contoh.go.id ADMIN_PASSWORD='Ganti!Password123' \
     LOGIN_WHITELIST="10.10.0.0/16" SSH_ALLOW="10.10.0.0/16" \
     MAIL_HOST=smtp.contoh.go.id MAIL_USERNAME=noreply@contoh.go.id MAIL_PASSWORD='...' \
     NONINTERACTIVE=1 bash /tmp/csirt/scripts/install-ubuntu.sh
```

| Variabel | Keterangan |
|---|---|
| `TLS_MODE` | `letsencrypt` (DNS publik dan port 80 harus terbuka), `custom` (isi `CERT_FILE` dan `KEY_FILE` dengan sertifikat instansi), `selfsigned` (uji/internal), atau `none` (HTTP saja, bukan untuk produksi) |
| `LOGIN_WHITELIST` | IP/CIDR yang boleh membuka `/csirt-login`. Default: jaringan privat. |
| `SSH_ALLOW` | IP/CIDR yang boleh SSH. **Isi untuk produksi**; bila kosong, SSH terbuka untuk semua IP. |
| `SOURCE_DIR` | Untuk server tanpa akses ke GitHub: salin kode ke server, lalu arahkan ke foldernya |
| `SKIP_FIREWALL=1` | Lewati UFW bila firewall dikelola perangkat jaringan |

Daftar lengkap variabel ada di bagian atas script. Script **aman dijalankan ulang**: database, `APP_KEY`, dan superadmin yang sudah ada tidak ditimpa. Kredensial admin tidak pernah ditulis ke `.env`.

Setelah selesai, ringkasan instalasi (termasuk password database) disimpan di `/root/csirt-install-info.txt` dan hanya bisa dibaca root.

### Update

```bash
sudo bash /var/www/csirt/scripts/update-ubuntu.sh
```

Urutannya: backup → mode pemeliharaan → `git pull` (atau `SOURCE_DIR=...`) → Composer dan build aset → migrasi → cache → reload PHP-FPM → situs online kembali. Bila salah satu langkah gagal, situs otomatis dikeluarkan dari mode pemeliharaan.

Untuk kedua cara, siapkan terlebih dahulu:

- Domain/subdomain, misalnya `csirt.contoh.go.id`.
- Sertifikat TLS (Let's Encrypt atau sertifikat instansi).
- Akun SMTP untuk mengirim notifikasi aduan.
- Daftar IP/subnet kantor atau VPN yang boleh membuka halaman login.

---

## A. Docker Compose

### 1. Prasyarat

- Docker Engine 24+ dan plugin Docker Compose v2
- RAM minimal 2 GB, disk 10 GB

### 2. Konfigurasi

```bash
git clone <repo> /opt/csirt && cd /opt/csirt   # atau salin folder template-csirt-v2
cp .env.docker.example .env
chmod 600 .env
```

Isi minimal di `.env`:

| Variabel | Keterangan |
|---|---|
| `APP_KEY` | Buat dengan `docker compose run --rm --no-deps app php artisan key:generate --show` |
| `APP_URL` | Alamat publik, wajib `https://` |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Password acak yang kuat, misalnya `openssl rand -hex 24` |
| `CSIRT_LOGIN_WHITELIST` | IP/CIDR kantor atau VPN, misalnya `10.10.0.0/16,203.0.113.5` |
| `MAIL_*` | Akun SMTP |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Superadmin pertama, dibuat otomatis saat start pertama |

### 3. Jalankan

```bash
docker compose up -d --build
docker compose logs -f app      # tunggu "Instalasi selesai" lalu "ready to handle connections"
```

Saat start, container `app` otomatis menjalankan:

1. `migrate --force`
2. `csirt:install`: membuat superadmin bila belum ada (idempoten)
3. `optimize`: cache config, route, view, dan event

Setelah superadmin terbuat, **hapus `ADMIN_PASSWORD` dari `.env`** lalu jalankan `docker compose up -d`.

### 4. Pasang TLS

Container `web` hanya mendengarkan `127.0.0.1:8080` (diatur lewat `HTTP_BIND`). Pasang Nginx di host sebagai terminator TLS dengan konfigurasi [nginx-host.conf.example](nginx-host.conf.example):

```bash
sudo apt install nginx certbot python3-certbot-nginx
sudo cp docs/nginx-host.conf.example /etc/nginx/sites-available/csirt
sudo ln -s /etc/nginx/sites-available/csirt /etc/nginx/sites-enabled/
sudo certbot --nginx -d csirt.contoh.go.id
sudo nginx -t && sudo systemctl reload nginx
```

Karena request sekarang melewati proxy host, aktifkan `set_real_ip_from` di [docker/nginx/default.conf](../docker/nginx/default.conf) dengan subnet jaringan Docker (lihat `docker network inspect csirt_backend`). Tanpa langkah ini, whitelist login dan rate limit akan melihat IP gateway Docker, bukan IP klien. Setelah itu rebuild: `docker compose up -d --build web`.

### 5. Login pertama

1. Buka `https://csirt.contoh.go.id/csirt-login` dari IP yang ada di whitelist.
2. Login dengan akun superadmin. Anda akan diarahkan ke **Keamanan Akun** untuk mengaktifkan 2FA (wajib).
3. Pindai QR code dengan aplikasi autentikator, lalu **simpan kode pemulihan** di tempat aman.
4. Lengkapi konten: **Kontak & Peta**, **Logo & Banner**, **Profil**, **Layanan**, **RFC 2350**, dan **Kunci PGP**.
5. Periksa `https://csirt.contoh.go.id/.well-known/security.txt`.

### 6. Operasional

```bash
# Perintah artisan
docker compose exec app php artisan <perintah>

# Update aplikasi
git pull && docker compose up -d --build

# Backup database
docker compose exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction csirt' | gzip > backup-$(date +%F).sql.gz

# Backup file unggahan & bukti aduan
docker run --rm -v csirt_public-uploads:/a -v csirt_private-storage:/b -v "$PWD":/out alpine \
  tar czf /out/files-$(date +%F).tar.gz -C / a b

# Log aplikasi (stderr container)
docker compose logs --since 1h app
```

### Struktur volume

| Volume | Isi | Di-mount ke |
|---|---|---|
| `db-data` | Data MariaDB | `db` |
| `public-uploads` | Logo, banner, artikel, PDF, kunci PGP | `app` (rw), `web` (ro) |
| `private-storage` | **Bukti aduan insiden** | Hanya `app`; tidak pernah dilayani Nginx |

---

## B. Instalasi Manual (Ubuntu 24.04)

### 1. Paket

```bash
sudo apt update
sudo apt install -y nginx mariadb-server unzip git \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-gd php8.3-intl php8.3-mbstring \
  php8.3-xml php8.3-zip php8.3-bcmath php8.3-curl php8.3-opcache
# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
# Node.js 22 (hanya untuk build aset; bisa dilakukan di mesin lain)
```

### 2. Database

```sql
CREATE DATABASE csirt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'csirt_app'@'localhost' IDENTIFIED BY '<password-kuat>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON csirt.* TO 'csirt_app'@'localhost';
```

### 3. Aplikasi

```bash
sudo mkdir -p /var/www/csirt && sudo chown $USER:www-data /var/www/csirt
cd /var/www/csirt   # salin isi template-csirt-v2 ke sini
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -rf node_modules
cp .env.example .env && chmod 640 .env
php artisan key:generate
# sunting .env: APP_URL, DB_*, MAIL_*, CSIRT_LOGIN_WHITELIST, dll.
php artisan csirt:install            # interaktif: membuat superadmin & konten awal
php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 750 {} \;
```

### 4. PHP-FPM

Salin pengaturan hardening dari [docker/php/security.ini](../docker/php/security.ini) ke `/etc/php/8.3/fpm/conf.d/99-csirt.ini`, lalu jalankan `sudo systemctl restart php8.3-fpm`.

### 5. Nginx

Gunakan [nginx-host.conf.example](nginx-host.conf.example) dengan opsi "server langsung" (blok `location` PHP yang sudah disiapkan di file tersebut), lalu pasang TLS dengan certbot.

### 6. Update

```bash
cd /var/www/csirt
php artisan down
git pull   # atau salin versi baru
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

---

## Konfigurasi Penting (`.env`)

| Variabel | Default | Keterangan |
|---|---|---|
| `CSIRT_LOGIN_WHITELIST` | `127.0.0.1,::1` | IP/CIDR yang boleh membuka `/csirt-login`. IP lain mendapat 404. |
| `TRUSTED_PROXIES` | kosong | Isi bila Laravel berada di belakang proxy HTTP (bukan FastCGI). |
| `CSIRT_ENFORCE_2FA` | `true` | Wajibkan 2FA untuk semua akun dashboard. |
| `CSIRT_TEAM_EMAIL` | kosong | Penerima notifikasi aduan baru. |
| `CSIRT_TICKET_PREFIX` | `CSIRT` | Format nomor tiket `PREFIX-YYYYMMDD-XXXXXX`. |
| `CSIRT_SECURITY_POLICY_URL` | kosong | URL kebijakan pengungkapan kerentanan (field `Policy` di security.txt). |
| `SESSION_LIFETIME` | `30` | Menit tidak aktif sebelum sesi berakhir. |

## Pemecahan Masalah

| Gejala | Penyebab & solusi |
|---|---|
| `/csirt-login` selalu 404 | IP Anda tidak ada di `CSIRT_LOGIN_WHITELIST`, atau IP yang terbaca adalah IP proxy (atur `set_real_ip_from`). Cek IP yang terbaca di Audit Log. |
| Error 419 setelah login | Pada akses lewat HTTP biasa, `SESSION_SECURE_COOKIE=true` membuat cookie tidak terkirim. Gunakan HTTPS. |
| Gambar captcha tidak muncul | Ekstensi `gd` belum terpasang. |
| Tampilan berantakan | Aset belum di-build (`npm run build`), atau `APP_URL` salah. |
| Kehilangan aplikasi autentikator | Gunakan kode pemulihan, atau minta superadmin mereset 2FA di menu Pengguna. |

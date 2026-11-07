# Panduan Deploy

Ada tiga cara memasang situs CSIRT. Pilih salah satu:

| Cara | Cocok untuk | Bagian |
|---|---|---|
| **Script otomatis** | Server Ubuntu 22.04/24.04 yang masih kosong (on-premise). **Paling disarankan.** | [1](#1-script-instalasi-otomatis-ubuntu) |
| **Docker Compose** | Server yang sudah memakai Docker | [2](#2-docker-compose) |
| **Manual** | Administrator yang ingin memasang langkah demi langkah | [3](#3-instalasi-manual-ubuntu) |

**Persiapan untuk semua cara:**

- Untuk **produksi**: domain/subdomain, misalnya `csirt.contoh.go.id`, yang mengarah ke IP server, serta sertifikat TLS (Let's Encrypt atau sertifikat instansi). Untuk uji coba lewat script Ubuntu, cukup alamat IP server.
- (Opsional) Akun SMTP, hanya bila ingin mengaktifkan notifikasi email aduan. Secara default aduan dikelola sepenuhnya melalui dashboard (lihat [Notifikasi Email](#notifikasi-email-opsional)).
- Daftar IP/subnet kantor atau VPN yang boleh membuka halaman login dan SSH.

---

## 1. Script Instalasi Otomatis (Ubuntu)

[`scripts/install-ubuntu.sh`](../scripts/install-ubuntu.sh) memasang seluruh stack di **Ubuntu Server 22.04 atau 24.04 LTS** yang masih kosong:

- Nginx, PHP-FPM 8.5 (pool khusus dengan `open_basedir`), MariaDB (hanya `127.0.0.1`), Composer, dan Node.js (hanya untuk build aset)
- Aplikasi di `/var/www/csirt`. Kode milik user `csirt` dan read-only bagi PHP; hanya `storage/` dan `bootstrap/cache/` yang bisa ditulis.
- fail2ban (SSH dan rate limit Nginx), unattended-upgrades, chrony
- Backup harian pukul 02:15 ke `/var/backups/csirt`, serta cron scheduler Laravel

**Mode sederhana (default).** Script sengaja dibuat minimal supaya cepat selesai dan mudah diuji:

- Situs dibuka lewat **alamat IP server** (`http://<IP-server>`). Tidak perlu DNS.
- **Tanpa HTTPS** (HTTP biasa). Sertifikat dipasang belakangan.
- **Firewall (UFW) tidak diaktifkan.** Aktifkan sendiri setelah instalasi.

Cocok untuk uji coba, VM lokal, atau pemasangan awal. Sebelum dipakai publik, selesaikan [bagian 1.4](#14-setelah-instalasi-dns-https-dan-firewall).

> **Tentang PHP 8.5:** repo resmi Ubuntu 22.04/24.04 belum menyediakan PHP 8.5, jadi script memasangnya dari PPA [`ondrej/php`](https://launchpad.net/~ondrej/+archive/ubuntu/php) (dikelola Ondřej Surý, maintainer paket PHP Debian). Repo ditambahkan langsung (tanpa `add-apt-repository`, yang memanggil API Launchpad dan sering timeout), dan fingerprint kunci diverifikasi sebelum dipakai. Patch keamanan PHP datang dari PPA ini, bukan dari Canonical. Script mendaftarkan PPA tersebut ke unattended-upgrades agar patch PHP ikut terpasang otomatis, dan memperingatkan bila versi PHP terpasang di bawah 8.5.12 (belum memuat tambalan CVE-2026-109618/109619). Pastikan penggunaan PPA ini sesuai kebijakan instansi Anda.

> **Jaringan dengan IPv6 rusak:** bila VM punya alamat IPv6 tetapi rutenya tidak berfungsi, `apt`/`curl` akan timeout. Script mendeteksi ini dan mematikan IPv6 sementara (sampai reboot) selama instalasi.

### 1.1 Mode interaktif

```bash
sudo apt install -y git
git clone https://github.com/VascoZ/masterweb-csirt-v2.git /tmp/csirt
sudo bash /tmp/csirt/scripts/install-ubuntu.sh
```

Script **tidak menanyakan apa pun**. Alamat situs diambil otomatis dari IP server, dan akun superadmin dibuat otomatis:

- Email: `admin@csirt.local`
- Password: **acak dan kuat**, ditampilkan di akhir instalasi dan disimpan di `/root/csirt-install-info.txt` (hanya root). Password bawaan yang sama untuk semua instalasi sengaja tidak dipakai karena kode ini publik.
- Saat login pertama, aktifkan 2FA lalu ganti email dan password lewat Dashboard → Keamanan Akun.

Untuk memakai email dan password sendiri, lihat mode non-interaktif di bawah. Kode aplikasi diambil dari GitHub ke `/var/www/csirt`. Folder `/tmp/csirt` hanya dipakai untuk menjalankan script dan boleh dihapus sesudahnya.

Setelah selesai, buka `http://<IP-server>` dan login di `/csirt-login`. Untuk melihat ulang kredensial: `sudo grep Superadmin /root/csirt-install-info.txt`.

### 1.2 Mode non-interaktif

```bash
sudo ADMIN_EMAIL=admin@contoh.go.id ADMIN_PASSWORD='Ganti!Password123' \
     NONINTERACTIVE=1 bash /tmp/csirt/scripts/install-ubuntu.sh
```

Contoh lengkap untuk produksi (domain, HTTPS, firewall, email):

```bash
sudo DOMAIN=csirt.contoh.go.id SITE_NAME="Contoh-CSIRT" \
     TLS_MODE=letsencrypt LE_EMAIL=admin@contoh.go.id \
     ADMIN_EMAIL=admin@contoh.go.id ADMIN_PASSWORD='Ganti!Password123' \
     LOGIN_WHITELIST="10.10.0.0/16" ENABLE_FIREWALL=1 SSH_ALLOW="10.10.0.0/16" \
     MAIL_HOST=smtp.contoh.go.id MAIL_USERNAME=noreply@contoh.go.id MAIL_PASSWORD='...' \
     NONINTERACTIVE=1 bash /tmp/csirt/scripts/install-ubuntu.sh
```

### 1.3 Variabel

| Variabel | Keterangan |
|---|---|
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Akun superadmin. Default: `admin@csirt.local` dan password acak. Password sendiri harus 12-72 karakter, huruf besar dan kecil, angka, simbol. |
| `DOMAIN` | Alamat situs. **Default: IP server** (terdeteksi otomatis). Isi dengan nama domain bila DNS sudah siap. |
| `TLS_MODE` | `none` (**default**, HTTP), `selfsigned` (uji/internal), `letsencrypt` (DNS publik dan port 80 harus terbuka), atau `custom` (isi `CERT_FILE` dan `KEY_FILE`) |
| `TRUSTED_HOSTS` | Host tambahan yang diterima aplikasi (selain `DOMAIN`). Pada mode IP, default-nya semua IPv4 server ini. Host lain mendapat 400. |
| `LOGIN_WHITELIST` | IP/CIDR yang boleh membuka `/csirt-login`. Default: jaringan privat (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`). |
| `ENABLE_FIREWALL=1` | Aktifkan UFW (default: **tidak** diaktifkan). Isi juga `SSH_ALLOW`. |
| `SSH_ALLOW` | IP/CIDR yang boleh SSH saat firewall diaktifkan. **Wajib diisi untuk produksi**; bila kosong, SSH terbuka untuk semua IP. |
| `SOURCE_DIR` | Untuk server tanpa akses ke GitHub: salin folder kode ke server, lalu arahkan ke folder itu |
| `MAIL_*`, `TEAM_EMAIL` | Opsional. Bila `MAIL_HOST` diisi, notifikasi email aduan diaktifkan (`CSIRT_MAIL_NOTIFICATIONS=true`). Bila kosong, aduan dikelola lewat dashboard saja. |

Daftar lengkap variabel ada di bagian atas script.

- Script **aman dijalankan ulang**: database, `APP_KEY`, dan superadmin yang sudah ada tidak ditimpa. Jalankan ulang dengan variabel baru untuk mengganti domain, HTTPS, atau firewall.
- Kredensial admin tidak pernah ditulis ke `.env`.
- Setelah selesai, ringkasan instalasi (termasuk password database) disimpan di `/root/csirt-install-info.txt`, yang hanya bisa dibaca root.
- Log lengkap instalasi: `/var/log/csirt-install.log`.

### 1.4 Setelah instalasi: DNS, HTTPS, dan firewall

Ketiganya dipasang belakangan. Cara termudah adalah **menjalankan ulang script** dengan variabel yang sesuai (data tidak hilang):

**a. Domain (DNS) dan HTTPS**

1. Buat record DNS `A` untuk domain yang mengarah ke IP server (atau, untuk uji coba, tambahkan `IP-server nama-domain` ke file hosts komputer Anda).
2. Jalankan ulang:
   ```bash
   sudo DOMAIN=csirt.contoh.go.id TLS_MODE=letsencrypt LE_EMAIL=admin@contoh.go.id \
        NONINTERACTIVE=1 bash /tmp/csirt/scripts/install-ubuntu.sh
   ```
   Gunakan `TLS_MODE=custom CERT_FILE=... KEY_FILE=...` untuk sertifikat instansi, atau `TLS_MODE=selfsigned` untuk uji coba. Script menulis ulang konfigurasi Nginx dan `APP_URL`.
3. Pastikan `https://<domain>/up` menjawab 200. Alamat IP lama tetap diterima aplikasi sebagai host tepercaya; hapus dari `CSIRT_TRUSTED_HOSTS` di `/var/www/csirt/.env` bila tidak diperlukan.

**b. Firewall (UFW)**

```bash
sudo ENABLE_FIREWALL=1 SSH_ALLOW="10.10.0.0/16" NONINTERACTIVE=1 \
     bash /tmp/csirt/scripts/install-ubuntu.sh
```

Atau manual (pastikan SSH diizinkan lebih dulu agar tidak terkunci):

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow from 10.10.0.0/16 to any port 22 proto tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

**c. Akses dari VirtualBox (mode NAT)**

Alamat NAT (`10.0.2.15`) tidak bisa dijangkau dari komputer host. Tambahkan **Port Forwarding** (Settings → Network → Advanced): Host `127.0.0.1:8081` ke Guest port `80`, lalu buka `http://127.0.0.1:8081`. Alternatifnya, pakai **Bridged Adapter** atau **Host-only Adapter** dan buka `http://<IP-VM>`.

**Bila instalasi gagal / timeout**

| Gejala | Penyebab dan solusi |
|---|---|
| `Gagal mengunduh kunci repositori PHP` | Server tidak bisa mencapai `keyserver.ubuntu.com`. Periksa internet dan DNS: `ping -c2 google.com`. |
| Berhenti lama di `apt`/`composer`/`npm` | Jaringan atau proxy. Pada proxy, set `https_proxy` dan `Acquire::https::Proxy` untuk apt. |
| Password sendiri ditolak | Tidak memenuhi syarat (12-72 karakter, huruf besar dan kecil, angka, simbol). Beri lewat `ADMIN_PASSWORD='...'` (kutip tunggal). |
| Port 80 tidak listen setelah selesai | `sudo nginx -t && sudo systemctl restart nginx`, lalu `sudo ss -tlnp \| grep :80`. |
| Situs 400 Bad Request | Alamat yang dibuka tidak ada di `APP_URL`/`CSIRT_TRUSTED_HOSTS` pada `/var/www/csirt/.env`. Setelah mengubahnya: `sudo -u www-data php /var/www/csirt/artisan optimize:clear`. |

### Update

```bash
# Bila diinstal dari GitHub (default)
sudo bash /var/www/csirt/scripts/update-ubuntu.sh

# Bila diinstal dengan SOURCE_DIR (tanpa git): salin kode baru ke server terlebih dahulu
sudo SOURCE_DIR=/path/kode-baru bash /var/www/csirt/scripts/update-ubuntu.sh
```

Urutannya: backup → mode pemeliharaan → ambil kode → Composer dan build aset → migrasi → cache → reload PHP-FPM → situs online kembali. Bila salah satu langkah gagal, situs otomatis dikeluarkan dari mode pemeliharaan.

### Perintah artisan

Di server hasil script, jalankan artisan sebagai `www-data` dari folder aplikasi:

```bash
cd /var/www/csirt && sudo -u www-data php artisan <perintah>
```

---

## 2. Docker Compose

### 2.1 Prasyarat

- Docker Engine 24+ dan plugin Docker Compose v2
- RAM minimal 2 GB, disk 10 GB

### 2.2 Konfigurasi

```bash
git clone https://github.com/VascoZ/masterweb-csirt-v2.git csirt
cd csirt
cp .env.docker.example .env
chmod 600 .env
echo "APP_KEY=base64:$(openssl rand -base64 32)"   # salin hasilnya ke APP_KEY di .env
openssl rand -hex 24                               # jalankan dua kali: untuk DB_PASSWORD & DB_ROOT_PASSWORD
```

Isi minimal di `.env`:

| Variabel | Keterangan |
|---|---|
| `APP_KEY` | Hasil perintah `openssl rand` di atas (diawali `base64:`) |
| `APP_URL` | Alamat publik, wajib `https://` |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Password acak yang kuat. Bila mengandung `$`, apit dengan kutip tunggal. |
| `CSIRT_LOGIN_WHITELIST` | IP/CIDR kantor atau VPN, misalnya `10.10.0.0/16,203.0.113.5` |
| `MAIL_*` | Opsional, hanya bila notifikasi email diaktifkan |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Superadmin pertama, dibuat otomatis saat container pertama kali berjalan |

### 2.3 Jalankan

```bash
docker compose up -d --build
docker compose logs -f app      # tunggu "Instalasi selesai" lalu "ready to handle connections"
```

Saat start, container `app` otomatis menjalankan:

1. `migrate --force`
2. `csirt:install`: membuat superadmin bila belum ada (idempoten)
3. `optimize`: cache config, route, view, dan event

Setelah superadmin terbuat, **hapus nilai `ADMIN_PASSWORD` dari `.env`** lalu jalankan `docker compose up -d`.

> **Uji coba lokal tanpa HTTPS** (`http://127.0.0.1:8080`): set `SESSION_SECURE_COOKIE=false` (tanpa itu login akan gagal dengan error 419), lalu tambahkan `172.16.0.0/12` ke `CSIRT_LOGIN_WHITELIST`, karena request dari host masuk dengan IP gateway Docker. Kembalikan kedua nilai ini sebelum produksi.

### 2.4 Pasang TLS dengan Nginx di host

Container `web` hanya mendengarkan `127.0.0.1:8080` (diatur lewat `HTTP_BIND`). Pasang Nginx di host sebagai terminator TLS dengan [nginx-host.conf.example](nginx-host.conf.example). Ganti `csirt.contoh.go.id` dengan domain Anda:

```bash
DOMAIN=csirt.contoh.go.id
LE_EMAIL=admin@contoh.go.id
sudo apt install -y nginx certbot
sudo mkdir -p /var/www/letsencrypt

# a. Konfigurasi sementara (HTTP saja) agar Let's Encrypt bisa memverifikasi domain
printf 'server {\n    listen 80;\n    listen [::]:80;\n    server_name %s;\n    location /.well-known/acme-challenge/ { root /var/www/letsencrypt; }\n}\n' "$DOMAIN" \
  | sudo tee /etc/nginx/sites-available/csirt >/dev/null
sudo ln -sf /etc/nginx/sites-available/csirt /etc/nginx/sites-enabled/csirt
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# b. Dapatkan sertifikat (metode webroot, perpanjangan otomatis tanpa menghentikan Nginx)
sudo certbot certonly --webroot -w /var/www/letsencrypt -d "$DOMAIN" --email "$LE_EMAIL" --agree-tos -n
printf '#!/bin/sh\nsystemctl reload nginx\n' | sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh >/dev/null
sudo chmod 755 /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh

# c. Pasang konfigurasi lengkap
sudo cp docs/nginx-host.conf.example /etc/nginx/sites-available/csirt
sudo sed -i "s/csirt.contoh.go.id/$DOMAIN/g" /etc/nginx/sites-available/csirt
sudo nginx -t && sudo systemctl reload nginx
sudo certbot renew --dry-run
```

Karena request sekarang melewati proxy host, IP klien asli harus diteruskan ke container. Buka [docker/nginx/default.conf](../docker/nginx/default.conf), lalu aktifkan tiga baris `set_real_ip_from`, `real_ip_header`, dan `real_ip_recursive`. Subnet default `172.16.0.0/12` mencakup jaringan Docker standar (cek dengan `docker network inspect csirt_backend`). Setelah itu jalankan `docker compose up -d --build web`. **Tanpa langkah ini, whitelist login dan rate limit akan melihat IP gateway Docker, bukan IP klien.**

### 2.5 Login pertama

1. Buka `https://csirt.contoh.go.id/csirt-login` dari IP yang ada di whitelist.
2. Login dengan akun superadmin. Anda akan diarahkan ke **Keamanan Akun** untuk mengaktifkan 2FA (wajib).
3. Pindai QR code dengan aplikasi autentikator, lalu **simpan kode pemulihan** di tempat aman.
4. Lengkapi konten: **Kontak & Peta**, **Logo & Banner**, **Profil**, **Layanan**, **RFC 2350**, dan **Kunci PGP**.
5. Periksa `https://csirt.contoh.go.id/.well-known/security.txt`.

### 2.6 Operasional

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

Backup pada mode Docker **tidak terjadwal otomatis**. Masukkan kedua perintah backup di atas ke cron host.

| Volume | Isi | Di-mount ke |
|---|---|---|
| `db-data` | Data MariaDB | `db` |
| `public-uploads` | Logo, banner, gambar artikel, PDF, kunci PGP | `app` (rw), `web` (ro) |
| `private-storage` | **Bukti aduan insiden** | Hanya `app`; tidak pernah dilayani Nginx |

---

## 3. Instalasi Manual (Ubuntu)

Bagian ini untuk administrator yang ingin memahami atau menyesuaikan setiap langkah. Script pada bagian 1 melakukan semua langkah ini, ditambah hardening yang lebih lengkap.

### 3.1 Paket

```bash
sudo apt update
sudo apt install -y software-properties-common ca-certificates curl gnupg git unzip
# PHP 8.5 belum ada di repo resmi Ubuntu; gunakan PPA ondrej/php
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update   # bila timeout, lihat bagian 1 (repo ditambah langsung)
sudo apt install -y nginx mariadb-server \
  php8.5-fpm php8.5-cli php8.5-mysql php8.5-gd php8.5-intl php8.5-mbstring \
  php8.5-xml php8.5-zip php8.5-bcmath php8.5-curl

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node.js 22 (hanya untuk build aset)
sudo install -d -m 0755 /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" | sudo tee /etc/apt/sources.list.d/nodesource.list
sudo apt update && sudo apt install -y nodejs
```

### 3.2 Database

```bash
sudo mariadb
```

```sql
CREATE DATABASE csirt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Aplikasi terhubung lewat 127.0.0.1; daftarkan juga 'localhost' (socket).
CREATE USER 'csirt_app'@'127.0.0.1' IDENTIFIED BY '<password-kuat>';
CREATE USER 'csirt_app'@'localhost' IDENTIFIED BY '<password-kuat>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON csirt.* TO 'csirt_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON csirt.* TO 'csirt_app'@'localhost';
FLUSH PRIVILEGES;
```

### 3.3 Aplikasi

```bash
sudo git clone https://github.com/VascoZ/masterweb-csirt-v2.git /var/www/csirt
sudo chown -R $USER:www-data /var/www/csirt
cd /var/www/csirt
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -rf node_modules
cp .env.example .env
php artisan key:generate --force
nano .env    # isi APP_URL, DB_PASSWORD, CSIRT_LOGIN_WHITELIST, dll. (MAIL_* opsional)
sudo chgrp www-data .env && chmod 640 .env   # hanya Anda & PHP yang bisa membaca

# storage/ dan bootstrap/cache/ harus bisa ditulis PHP
sudo chown -R www-data:www-data storage bootstrap/cache
ln -sfn ../storage/app/public public/storage

# Mulai sekarang jalankan artisan sebagai www-data
sudo -u www-data php artisan csirt:install   # interaktif: migrasi, superadmin, konten awal
sudo -u www-data php artisan optimize
```

### 3.4 PHP-FPM

```bash
sudo cp docker/php/security.ini /etc/php/8.5/fpm/conf.d/99-csirt.ini
# Hapus baris error_log (khusus Docker); error PHP akan tercatat di error log Nginx.
sudo sed -i '/^error_log/d' /etc/php/8.5/fpm/conf.d/99-csirt.ini
sudo systemctl restart php8.5-fpm
```

Pengaturan `pm.max_requests = 200` (lihat [docker/php/www.conf](../docker/php/www.conf)) berada di file pool `/etc/php/8.5/fpm/pool.d/www.conf`, bukan di `conf.d`.

### 3.5 Nginx dan TLS

Gunakan [nginx-host.conf.example](nginx-host.conf.example) dengan **mode (2)**: hapus blok `location /` mode (1), lalu aktifkan (hapus `#`) blok mode (2) beserta dua baris `limit_req_zone` di bagian atas. Dapatkan sertifikat dan pasang konfigurasinya seperti langkah [2.4](#24-pasang-tls-dengan-nginx-di-host) (a–c).

### 3.6 Update

```bash
cd /var/www/csirt
sudo -u www-data php artisan down
git pull
# --no-scripts: bootstrap/cache milik www-data, jadi package:discover dijalankan sebagai www-data
composer install --no-dev --optimize-autoloader --no-scripts
sudo -u www-data php artisan package:discover
npm ci && npm run build && rm -rf node_modules
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize
sudo systemctl reload php8.5-fpm   # OPcache memakai validate_timestamps=0
sudo -u www-data php artisan up
```

---

## Konfigurasi Penting (`.env`)

| Variabel | Nilai di contoh | Keterangan |
|---|---|---|
| `CSIRT_LOGIN_WHITELIST` | `127.0.0.1,::1` | IP/CIDR yang boleh membuka `/csirt-login`. IP lain mendapat 404. |
| `TRUSTED_PROXIES` | kosong | Isi bila Laravel berada di belakang proxy HTTP. Tidak perlu untuk Nginx → PHP-FPM (FastCGI). |
| `CSIRT_ENFORCE_2FA` | `true` | Wajibkan 2FA untuk semua akun dashboard. |
| `CSIRT_SESSION_ABSOLUTE_MINUTES` | `480` | Batas umur sesi sejak login (menit), terlepas dari aktivitas. `0` = tanpa batas. Dapat ditimpa dari dashboard (skema durasi sesi). |
| `CSIRT_TRUSTED_HOSTS` | kosong | Host tambahan yang diterima selain host `APP_URL` dan loopback, mis. alamat IP server: `10.10.1.5,csirt.instansi.go.id`. Host lain mendapat 400. |
| `CSIRT_CANONICAL_REDIRECT` | `true` | Alihkan `127.0.0.1`/`::1` ke host `APP_URL` agar cookie sesi tidak terpecah antar host. |
| `CSIRT_SECURITY_LOG_CHANNEL` | `security` | Tujuan log keamanan JSON: `security` (file), `security_stderr` (Docker), atau `syslog`. |
| `CSIRT_RETENTION_AUDIT_LOG_DAYS` | `730` | Hapus audit log lebih lama dari ini. `0` = simpan selamanya. Dapat diubah dari dashboard. |
| `CSIRT_RETENTION_CLOSED_TICKET_DAYS` | `0` | Anonimkan data pribadi aduan yang ditutup lebih lama dari ini. `0` = tidak pernah. Dapat diubah dari dashboard. |
| `CSIRT_MAIL_NOTIFICATIONS` | `false` | Kirim email tanda terima/pembaruan ke pelapor dan pemberitahuan ke tim. |
| `CSIRT_TEAM_EMAIL` | kosong | Penerima notifikasi aduan baru (hanya bila `CSIRT_MAIL_NOTIFICATIONS=true`). |
| `CSIRT_TICKET_PREFIX` | `CSIRT` | Format nomor tiket `PREFIX-YYYYMMDD-XXXXXX`. |
| `CSIRT_SECURITY_POLICY_URL` | kosong | URL kebijakan pengungkapan kerentanan (field `Policy` di security.txt). |
| `SESSION_LIFETIME` | `30` | Menit tidak aktif sebelum sesi berakhir. |

Setelah mengubah `.env`, jalankan `php artisan optimize` (atau `docker compose up -d` pada Docker) agar cache konfigurasi diperbarui.

## Pengaturan Sistem (Dashboard, Superadmin)

Menu **Dashboard → Pengaturan Sistem** (khusus superadmin) menampilkan dan mengatur:

- **Status sistem**: apakah scheduler benar-benar berjalan (detak tiap menit), versi PHP terhadap tambalan CVE, koneksi database, mode debug, HTTPS, kewajiban 2FA, whitelist login, ruang disk, dan hasil retensi terakhir. Item bermasalah diberi peringatan beserta cara memperbaikinya.
- **Tugas terjadwal**: aktif/nonaktif, jam eksekusi, tombol **Simulasi** (dry-run: hanya menghitung) dan **Jalankan** manual, serta riwayat eksekusi.
- **Skema durasi sesi**: Ketat (tidak aktif 20 menit, maks. 4 jam), Standar (30 menit, 8 jam), Longgar (2 jam, 24 jam), Kustom, atau ikut `.env`. Berlaku untuk semua sesi, termasuk pengunjung situs publik.
- **Fitur**: buka/tutup penerimaan aduan baru (mis. saat pemeliharaan), notifikasi email, retensi data.

Perubahan berlaku tanpa restart dan tercatat di Audit Log. **Whitelist login, kewajiban 2FA, host dan proxy tepercaya sengaja tidak bisa diubah dari web**: kesalahan di sana dapat mengunci semua pengguna atau melemahkan keamanan, jadi hanya lewat `.env` di server.

**Scheduler.** Pada script on-premise, scheduler dijalankan cron tiap menit (`/etc/cron.d/csirt`). Pada Docker, service `scheduler` memanggil `schedule:run` tiap menit. Jangan mengganti dengan `schedule:work`: perintah itu memakai `proc_open` yang dimatikan oleh hardening PHP.

## Notifikasi Email (Opsional)

Secara default **tidak ada email yang dikirim**. Alur kerjanya:

- Petugas memantau, membaca, dan menindaklanjuti aduan di **Dashboard → Aduan**.
- Pelapor menyimpan nomor tiket yang tampil setelah mengirim aduan, lalu memantau perkembangan di halaman **Cek Status** (`/aduan/status`) dengan nomor tiket dan email.

Untuk mengaktifkan email (tanda terima dan pembaruan status ke pelapor, serta pemberitahuan aduan baru ke tim):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.instansi.go.id
MAIL_PORT=587
MAIL_USERNAME=noreply@instansi.go.id
MAIL_PASSWORD='...'
MAIL_FROM_ADDRESS="noreply@instansi.go.id"
CSIRT_MAIL_NOTIFICATIONS=true
CSIRT_TEAM_EMAIL=tim-csirt@instansi.go.id   # opsional
```

Lalu jalankan `php artisan optimize`. Isi aduan tidak pernah disertakan dalam email; email hanya berisi nomor tiket, status, dan tautan.

## Pemecahan Masalah

| Gejala | Penyebab & solusi |
|---|---|
| `/csirt-login` selalu 404 | IP Anda tidak ada di `CSIRT_LOGIN_WHITELIST`, atau IP yang terbaca adalah IP proxy/gateway Docker (atur `set_real_ip_from`). Lihat IP yang terbaca di access log Nginx: `/var/log/nginx/csirt.access.log` (on-premise) atau `docker compose logs web` (Docker). |
| Sesi admin tampak hilang saat membuka situs utama | Cookie sesi terikat ke nama host: `localhost` dan `127.0.0.1` adalah dua host berbeda. Sistem mengalihkan alias loopback ke host `APP_URL` secara otomatis; pastikan `APP_URL` sesuai alamat yang Anda pakai. |
| 400 Bad Request di semua halaman | Host pada URL tidak dikenal. Akses lewat domain `APP_URL`, atau daftarkan alamat IP/domain tambahan di `CSIRT_TRUSTED_HOSTS`, lalu `php artisan optimize`. |
| Status "Scheduler belum pernah berjalan" di Pengaturan Sistem | Docker: `docker compose ps scheduler` dan `docker compose logs scheduler`. On-premise: `systemctl status cron` dan isi `/etc/cron.d/csirt`. |
| Error 419 setelah login | Situs diakses lewat HTTP biasa, sedangkan `SESSION_SECURE_COOKIE=true` membuat cookie tidak terkirim. Gunakan HTTPS. |
| Gambar captcha tidak muncul | Ekstensi `gd` belum terpasang. |
| Tampilan berantakan | Aset belum di-build (`npm run build`), atau `APP_URL` salah. |
| Lupa password / kehilangan aplikasi autentikator | Gunakan kode pemulihan, minta superadmin lain mereset 2FA di menu **Pengguna**, atau pulihkan dari server: `php artisan csirt:reset-admin email@instansi.go.id` (lihat bawah). |

### Memulihkan akses superadmin dari server

`csirt:reset-admin` mengatur ulang password, mereset 2FA, dan menjadikan akun tersebut superadmin. Tindakan ini tercatat di audit log.

```bash
# Script on-premise
cd /var/www/csirt && sudo -u www-data php artisan csirt:reset-admin admin@instansi.go.id

# Docker
docker compose exec app php artisan csirt:reset-admin admin@instansi.go.id

# Sekaligus mengganti email akun
... php artisan csirt:reset-admin admin@gmail.com --new-email=admin@instansi.go.id
```

Password baru ditanyakan secara interaktif. Bila email tidak ditemukan, daftar akun yang ada akan ditampilkan.

# Checklist Hardening Server

Checklist ini melengkapi pengamanan yang sudah ada di dalam aplikasi (CSP, 2FA, policy, rate limit, dan sanitasi). Centang setiap butir sebelum situs dibuka ke publik.

## 1. Aplikasi

- [ ] `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] `APP_URL` memakai `https://`
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- [ ] `CSIRT_ENFORCE_2FA=true`
- [ ] `CSIRT_LOGIN_WHITELIST` hanya berisi jaringan kantor/VPN, **bukan** `*`
- [ ] `ADMIN_PASSWORD` sudah dihapus dari `.env` setelah instalasi
- [ ] `.env` ber-permission `600`/`640` dan tidak ada di dalam folder `public/`
- [ ] Akun default/contoh dari versi 2022 (mis. `admin@gmail.com`, user Faker) dihapus atau passwordnya diganti, lalu 2FA diaktifkan
- [ ] `php artisan csirt:install` dijalankan ulang untuk melihat peringatan konfigurasi
- [ ] Kunci PGP dan RFC 2350 sudah diunggah; `security.txt` menampilkan kontak yang benar

## 2. TLS dan Header (Nginx host)

Sudah ada di [nginx-host.conf.example](nginx-host.conf.example):

- [ ] Hanya TLS 1.2 dan 1.3; HTTP dialihkan ke HTTPS
- [ ] HSTS `max-age=31536000; includeSubDomains` (tambahkan `preload` hanya bila yakin)
- [ ] OCSP stapling aktif
- [ ] `server_tokens off`
- [ ] Uji di <https://www.ssllabs.com/ssltest/> (target A/A+) dan <https://securityheaders.com>

Header yang dikirim aplikasi:

| Header | Nilai |
|---|---|
| `Content-Security-Policy` | `default-src 'self'`, script/style dengan nonce, `object-src 'none'`, `frame-ancestors 'self'` |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | kamera, mikrofon, geolokasi, pembayaran, dan USB dinonaktifkan |
| `Cross-Origin-Opener-Policy` | `same-origin` |
| `Strict-Transport-Security` | otomatis bila request terdeteksi HTTPS |
| `Cache-Control` | `no-store` untuk halaman yang memerlukan login |

## 3. Sistem Operasi

- [ ] Pembaruan keamanan otomatis: `sudo apt install unattended-upgrades && sudo dpkg-reconfigure unattended-upgrades`
- [ ] Firewall hanya membuka 22 (dibatasi IP admin), 80, dan 443:
  ```bash
  sudo ufw default deny incoming
  sudo ufw allow from 10.10.0.0/16 to any port 22 proto tcp
  sudo ufw allow 80,443/tcp
  sudo ufw enable
  ```
- [ ] SSH: `PasswordAuthentication no`, `PermitRootLogin no`, login dengan kunci
- [ ] `fail2ban` untuk SSH dan Nginx (filter `nginx-limit-req` menangkap pelanggaran rate limit)
- [ ] Port database (3306) tidak terbuka ke internet. Di Docker, `db` tidak dipublikasikan.
- [ ] Sinkronisasi waktu (NTP/chrony) aktif. **TOTP 2FA bergantung pada jam yang akurat.**

## 4. PHP

Sudah diterapkan di image Docker ([docker/php/security.ini](../docker/php/security.ini)). Untuk instalasi manual, salin ke `conf.d`:

- `expose_php = Off`, `display_errors = Off`
- `allow_url_fopen = Off`, `allow_url_include = Off`
- `disable_functions = exec,passthru,shell_exec,system,proc_open,popen,...`
- `session.use_strict_mode = 1`
- Batas unggah `upload_max_filesize = 10M`, `post_max_size = 30M`
- OPcache aktif dengan `validate_timestamps = 0`. Setelah update kode, jalankan `systemctl reload php8.3-fpm`.

## 5. Container (bila memakai Docker)

Sudah diterapkan di [docker-compose.yml](../docker-compose.yml):

- Root filesystem `read_only`; direktori tulis memakai `tmpfs` atau volume
- `cap_drop: [ALL]` dan `no-new-privileges`
- PHP-FPM berjalan sebagai `www-data`, Nginx sebagai `nginx` (non-root, port 8080)
- Bukti aduan (`private-storage`) tidak di-mount ke container Nginx
- Database hanya di jaringan internal `backend`

Tambahan yang disarankan:

- [ ] Pindai image secara berkala: `docker scout cves csirt-app:latest` atau `trivy image csirt-app:latest`
- [ ] Rebuild image minimal sebulan sekali untuk mendapatkan patch base image

## 6. Backup dan Pemulihan

- [ ] Backup harian database dan kedua volume file (lihat [DEPLOY.md](DEPLOY.md#6-operasional))
- [ ] Backup dienkripsi (mis. `gpg --symmetric` atau `age`) dan disimpan di luar server
- [ ] **Uji pemulihan** minimal tiap 3 bulan
- [ ] Retensi bukti aduan mengikuti kebijakan instansi dan peraturan yang berlaku

## 7. Pemantauan

- [ ] Pantau `/up` (health check Laravel) dari luar
- [ ] Tinjau **Dashboard → Audit Log** secara berkala, terutama `login_failed`, `2fa_failed`, `2fa_reset`, dan `attachment_downloaded`
- [ ] Kirim log Nginx dan aplikasi ke SIEM/log terpusat bila tersedia
- [ ] Langganan advisory keamanan Laravel, PHP, dan Nginx; jalankan `composer audit` dan `npm audit` setiap update

## 8. Penanganan Lampiran Aduan

Lampiran berasal dari pihak luar dan dapat berisi malware.

- Lampiran selalu diunduh sebagai `application/octet-stream` (tidak pernah dibuka langsung di browser).
- Hash SHA-256 ditampilkan untuk menjaga integritas barang bukti.
- **Buka lampiran hanya di lingkungan analisis terisolasi** (VM sandbox), bukan di laptop kerja.

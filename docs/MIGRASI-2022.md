# Migrasi dari MasterWebCSIRT 2022

Struktur tabel lama dipertahankan. Database 2022 bisa langsung dipakai, lalu `php artisan migrate` menambahkan tabel dan kolom baru tanpa mengubah data lama.

Prosedur ini sudah diuji dengan `template_csirt.sql` asli.

## Yang Berubah

| Aspek | 2022 | v2 |
|---|---|---|
| Framework | Laravel 8, PHP 7.3/8.0 | Laravel 12, PHP 8.5 |
| Login | `/csirt-login`, whitelist hardcode | `/csirt-login`, whitelist dari `.env` (mendukung CIDR), 2FA wajib |
| Registrasi user | `/register` | Dashboard → Pengguna (superadmin). `/register` dialihkan ke sana. |
| Ganti password | `/register/showChangePasswordGet` | Dashboard → Keamanan Akun |
| Manajemen user | Admin | **Superadmin** saja |
| Penulis (non-admin) | Bisa menerbitkan langsung | Hanya menyimpan draf; admin yang menerbitkan |
| Embed Maps | HTML iframe disimpan mentah | Hanya URL embed Google Maps yang disimpan; iframe dibangun ulang |
| Tombol "Lapor Insiden" | Tautan eksternal dari kolom `link` di Profil | Selalu ke form aduan internal `/aduan`. Kolom `link` lama dibiarkan di database tetapi tidak dipakai lagi. |
| URL publik | `/profil`, `/file`, `/service`, `/guidance`, `/contact`, `/posts` | **Tetap sama**, ditambah `/aduan`, `/advisories`, `/.well-known/security.txt` |

Yang perlu disiapkan dari server lama:

- File dump database, misalnya `template_csirt.sql` (ekspor dari phpMyAdmin atau `mysqldump`)
- Isi folder `storage/app/public/` (logo, banner, gambar artikel, PDF RFC 2350, panduan, kunci PGP)

> **Penting:** dump harus di-import ke database yang **masih kosong**. Superadmin yang dibuat installer pada instalasi baru **akan hilang** karena tabelnya diganti data lama. Pakai akun dari data lama, lalu pulihkan aksesnya dengan `csirt:reset-admin` (langkah terakhir).

## Langkah Migrasi (Docker)

```bash
# 1. Siapkan .env (lihat DEPLOY.md bagian 2.2). Kosongkan ADMIN_EMAIL dan ADMIN_PASSWORD.
cp .env.docker.example .env

# 2. Jalankan database saja dan tunggu sampai siap
docker compose up -d --wait db

# 3. Import dump lama ke database (masih kosong)
set -a; . ./.env; set +a
docker compose exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" csirt < /path/ke/template_csirt.sql

# 4. Jalankan aplikasi (migrasi tabel & kolom baru berjalan otomatis)
docker compose up -d --build

# 5. Salin file unggahan lama
docker compose cp /path/ke/storage/app/public/. app:/var/www/html/storage/app/public/
docker compose exec app php artisan cache:clear

# 6. Pulihkan akses superadmin lama (password ditanyakan; 2FA direset)
docker compose exec app php artisan csirt:reset-admin admin@gmail.com --new-email=admin@instansi.go.id
```

## Langkah Migrasi (server hasil `install-ubuntu.sh`)

```bash
cd /var/www/csirt

# 1. Kosongkan database hasil instalasi baru, lalu import dump lama
sudo mariadb -e "DROP DATABASE csirt; CREATE DATABASE csirt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mariadb csirt < /path/ke/template_csirt.sql

# 2. Tambahkan tabel & kolom baru
sudo -u www-data php artisan migrate --force

# 3. Salin file unggahan lama
sudo rsync -a /path/ke/storage/app/public/ storage/app/public/
sudo chown -R www-data:www-data storage/app/public

# 4. Bersihkan cache & pulihkan akses superadmin lama
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan optimize
sudo -u www-data php artisan csirt:reset-admin admin@gmail.com --new-email=admin@instansi.go.id
```

Untuk instalasi manual (DEPLOY.md bagian 3), langkahnya sama.

## Setelah Migrasi (Wajib)

1. **Akun lama.** Dump 2022 berisi akun `admin@gmail.com` (superadmin) dan beberapa user hasil Faker.
   - Pulihkan akses superadmin dengan `csirt:reset-admin` (langkah terakhir di atas). Password lama biasanya tidak diketahui, dan `csirt:install` tidak membuat superadmin baru bila sudah ada superadmin.
   - Setelah login, buka **Pengguna** dan ganti password akun yang tidak dikenal ke nilai acak, atau cabut peran adminnya.
2. **2FA.** Semua akun akan diminta mengaktifkan 2FA pada login pertama.
3. **Kontak & Peta.** Buka lalu simpan ulang data kontak agar field Maps dinormalisasi menjadi URL embed.
4. **Konten lama.** Isi artikel/profil/layanan lama disanitasi saat ditampilkan. Script atau atribut berbahaya yang mungkin tersisa dari versi lama tidak akan dieksekusi.
5. **File lama** tetap memakai nama aslinya. File yang diunggah setelah migrasi memakai nama acak.
6. Periksa `/.well-known/security.txt`. Kontak dan kunci PGP diambil dari data lama.

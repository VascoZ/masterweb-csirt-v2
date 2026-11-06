# Migrasi dari MasterWebCSIRT 2022

Struktur tabel lama dipertahankan. Database 2022 bisa langsung dipakai, lalu `php artisan migrate` menambahkan tabel dan kolom baru tanpa mengubah data lama.

Prosedur ini sudah diuji dengan `template_csirt.sql` asli.

## Yang Berubah

| Aspek | 2022 | v2 |
|---|---|---|
| Framework | Laravel 8, PHP 7.3/8.0 | Laravel 12, PHP 8.3 |
| Login | `/csirt-login`, whitelist hardcode | `/csirt-login`, whitelist dari `.env` (mendukung CIDR), 2FA wajib |
| Registrasi user | `/register` | Dashboard → Pengguna (superadmin). `/register` dialihkan ke sana. |
| Ganti password | `/register/showChangePasswordGet` | Dashboard → Keamanan Akun |
| Manajemen user | Admin | **Superadmin** saja |
| Penulis (non-admin) | Bisa menerbitkan langsung | Hanya menyimpan draf; admin yang menerbitkan |
| Embed Maps | HTML iframe disimpan mentah | Hanya URL embed Google Maps yang disimpan; iframe dibangun ulang |
| Tombol "Lapor Insiden" | Tautan eksternal dari kolom `link` di Profil | Selalu ke form aduan internal `/aduan`. Kolom `link` lama dibiarkan di database tetapi tidak dipakai lagi. |
| URL publik | `/profil`, `/file`, `/service`, `/guidance`, `/contact`, `/posts` | **Tetap sama**, ditambah `/aduan`, `/advisories`, `/.well-known/security.txt` |

## Langkah Migrasi (Docker)

```bash
# 1. Siapkan .env dan jalankan database saja
cp .env.docker.example .env      # isi APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, dll.
# Kosongkan ADMIN_EMAIL agar installer tidak membuat superadmin baru
docker compose up -d db

# 2. Import dump lama ke database kosong
set -a; . ./.env; set +a
docker compose exec -T db mariadb -uroot -p"$DB_ROOT_PASSWORD" csirt < /path/ke/template_csirt.sql

# 3. Jalankan aplikasi (migrasi tabel baru berjalan otomatis)
docker compose up -d --build

# 4. Salin file unggahan lama (storage/app/public dari server lama)
docker compose cp /path/ke/template-csirt/storage/app/public/. app:/var/www/html/storage/app/public/
docker compose exec app php artisan cache:clear
```

## Langkah Migrasi (Manual)

```bash
mysql -u root -p csirt < template_csirt.sql
php artisan migrate --force
rsync -a /path/lama/storage/app/public/ storage/app/public/
php artisan storage:link
php artisan cache:clear && php artisan optimize
```

## Setelah Migrasi (Wajib)

1. **Akun lama.** Dump 2022 berisi akun `admin@gmail.com` (superadmin) dan beberapa user hasil Faker.
   - Login sebagai superadmin lama, lalu ubah email dan password, atau buat superadmin baru dengan `php artisan csirt:install`.
   - Nonaktifkan akun yang tidak dikenal: ubah password-nya ke nilai acak, atau hapus lewat database.
2. **2FA.** Semua akun akan diminta mengaktifkan 2FA pada login pertama.
3. **Kontak & Peta.** Buka lalu simpan ulang data kontak agar field Maps dinormalisasi menjadi URL embed.
4. **Konten lama.** Isi artikel/profil/layanan lama disanitasi saat ditampilkan. Script atau atribut berbahaya yang mungkin tersisa dari versi lama tidak akan dieksekusi.
5. **File lama** tetap memakai nama aslinya. File yang diunggah setelah migrasi memakai nama acak.
6. Periksa `/.well-known/security.txt`. Kontak dan kunci PGP diambil dari data lama.

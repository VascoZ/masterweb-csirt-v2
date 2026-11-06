# Kebijakan Keamanan

## Melaporkan kerentanan

Jika Anda menemukan kerentanan pada template ini, **jangan membuat issue publik**. Laporkan secara privat melalui:

- GitHub: tab **Security → Report a vulnerability** pada repository ini (Private Vulnerability Reporting), atau
- email ke pengelola repository (lihat profil GitHub pengelola).

Sertakan: versi/commit, langkah reproduksi, dampak yang Anda perkirakan, dan (bila ada) usulan perbaikan. Mohon tidak mengakses data pihak lain dan tidak melakukan uji yang mengganggu layanan.

**Yang dapat Anda harapkan:** konfirmasi penerimaan dalam 5 hari kerja, penilaian awal dalam 14 hari, dan kabar perkembangan sampai perbaikan dirilis. Kami akan mencantumkan nama Anda pada catatan rilis bila Anda berkenan.

## Versi yang didukung

Hanya cabang `main` yang menerima perbaikan keamanan.

## Cakupan

Dalam cakupan: kode aplikasi, skrip instalasi (`scripts/`), konfigurasi Docker dan Nginx contoh, serta dokumentasi hardening. Di luar cakupan: kerentanan pada Laravel, PHP, Nginx, atau paket pihak ketiga (laporkan ke pengelolanya; kami akan memperbarui dependensi setelah perbaikan tersedia), dan konfigurasi server milik pengguna template.

## Praktik keamanan yang diterapkan

Ringkasan kontrol dan pemetaannya ke OWASP ASVS, ISO/IEC 27001:2022, dan NIST ada di [docs/KEPATUHAN-KEAMANAN.md](docs/KEPATUHAN-KEAMANAN.md). Checklist hardening server ada di [docs/HARDENING.md](docs/HARDENING.md).

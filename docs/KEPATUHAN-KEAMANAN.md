# Kepatuhan & Pemetaan Standar Keamanan

Dokumen ini memetakan kontrol keamanan template ke **OWASP ASVS 5.0 / OWASP Top 10:2021**, **ISO/IEC 27001:2022 Annex A**, dan **NIST** (SP 800-53 Rev. 5, SP 800-63B, SSDF SP 800-218).

> **Batasan penting.** Template ini menyediakan *kontrol teknis* yang mendukung standar tersebut. **Template tidak membuat instansi otomatis "patuh" atau "tersertifikasi" ISO 27001**, karena sertifikasi menilai sistem manajemen organisasi (kebijakan, peran, manajemen risiko, audit internal), bukan satu aplikasi. Kolom "Status" hanya menyatakan apa yang ada di kode dan sudah diuji; kolom "Tanggung jawab instansi" menyatakan apa yang tetap harus dikerjakan di luar aplikasi.

## 1. Hasil analisis whitebox

Tinjauan dilakukan terhadap seluruh kode `app/`, `routes/`, `config/`, `resources/views/`, skrip instalasi, dan konfigurasi Docker/Nginx, ditambah SAST dengan Semgrep (aturan `p/php`, `p/secrets`, `p/nginx`, `p/dockerfile`, `p/javascript`). Semgrep sendiri hanya menghasilkan 2 temuan (keduanya `$host` Nginx); sisanya ditemukan lewat pembacaan kode dan **dibuktikan terlebih dulu** sebelum diperbaiki.

| # | Temuan | Tingkat | Bukti | Perbaikan | Test |
|---|---|---|---|---|---|
| 1 | **Host header poisoning.** URL di redirect dan email dibangun dari header `Host` klien. Dengan `Host: evil.example`, `route()` menghasilkan `http://evil.example/...` (CWE-644) | Sedang | Simulasi request | `trustHosts` dengan daftar eksplisit (`APP_URL` + loopback + `CSIRT_TRUSTED_HOSTS`; selain itu 400), Nginx `default_server` menolak (444), redirect memakai `$server_name` | `test_unknown_host_header_is_rejected...` |
| 2 | **Injeksi konten ke email resmi.** Nama pelapor dimasukkan ke sapaan email yang dirender Markdown, dan alamat tujuan juga dari pelapor, sehingga memungkinkan tautan phishing dalam email dari domain CSIRT | Sedang | Dibaca dari kode; belum diuji sampai ke inbox | Sapaan statis, tanpa input pelapor | `test_reporter_input_never_reaches...` |
| 3 | **Regex embed Maps terlalu longgar.** `google\.[a-z.]+` menerima `google.com.domain-penyerang.tld` | Rendah (CSP `frame-src` membatasi dampak) | `mapsUrl()` mengembalikan URL itu | Host harus persis `www.google.com`, HTTPS | `test_maps_embed_only_accepts_google_host` |
| 4 | **Penebakan kode 2FA.** Pembatas laju membaca kunci sesi lama sehingga praktis hanya per IP; penyerang berganti IP bisa menebak 6 digit | Sedang | Dibaca dari kode | Batas per akun (5/menit) dan per IP, plus login dibatalkan setelah 5 kode salah | `test_two_factor_rate_limit_is_per_account...`, `..._cancelled_after_repeated_wrong_codes` |
| 5 | **Enumerasi akun lewat waktu respons.** `Hash::check` tidak dijalankan bila email tidak terdaftar (CWE-208) | Rendah | Dibaca dari kode | Hash tiruan selalu diperiksa | — |
| 6 | **Kebijakan password lemah.** Minimal 8 karakter; di atas 72 byte dipotong diam-diam oleh bcrypt | Sedang | `BcryptHasher` | Min. 12, maks. 72, cek kebocoran di produksi; skrip instalasi disamakan | `test_password_policy_follows_asvs...` |
| 7 | **Tidak ada pencabutan akses.** Tidak bisa menonaktifkan akun; sesi aktif tetap hidup setelah password diganti atau 2FA direset; tidak ada batas umur sesi absolut | Sedang | Dibaca dari kode | Status akun aktif/nonaktif, semua sesi diakhiri pada perubahan penting, batas sesi absolut 8 jam | `test_session_expires_after_absolute_lifetime` |
| 8 | **Penolakan akses tidak tercatat.** Percobaan 403 tidak meninggalkan jejak | Rendah | Dibaca dari kode | Event `access_denied` di audit log | `test_access_denied_is_audited` |
| 9 | **Data pribadi pelapor tersalin permanen ke audit log** tanpa batas simpan | Sedang (UU PDP) | Dibaca dari kode | Nilai field pribadi disamarkan; `csirt:prune` menghapus log lama dan menganonimkan aduan yang sudah ditutup | `test_reporter_personal_data_is_not_copied...`, `test_prune_...` |
| 10 | **Penugasan tiket menerima sembarang user ID** (penulis atau akun nonaktif) | Rendah | Dibaca dari kode | Hanya admin/superadmin aktif | `test_ticket_can_only_be_assigned_to_active_staff` |
| 11 | Header isolasi lintas-origin belum lengkap | Rendah | Header respons | `Cross-Origin-Resource-Policy`, `X-Permitted-Cross-Domain-Policies` | `test_security_headers_present` |
| 12 | **Editor Trix rusak oleh CSP.** Trix menyisipkan `<style>` lewat JavaScript tanpa nonce, sehingga diblokir: dialog tautan tampil terus dan kolom "Enter a URL" terkunci (8 pelanggaran CSP). Efek samping dari pengetatan CSP | Sedang (fungsional) | Diukur di browser sungguhan: dialog `display:block` saat tertutup | Meta `csp-nonce` di layout dashboard + aturan dasar Trix disalin ke CSS sendiri; 0 pelanggaran CSP | Diuji di browser (Playwright), bukan PHPUnit |
| 13 | **Sesi admin tampak hilang.** Cookie sesi terikat ke nama host; berpindah `localhost` ↔ `127.0.0.1` memakai wadah cookie berbeda | Sedang (fungsional) | Direproduksi di browser: login di `localhost`, buka situs lewat `127.0.0.1`, dashboard meminta login | Alias loopback dialihkan ke host `APP_URL` (`CSIRT_CANONICAL_REDIRECT`) | `test_loopback_aliases_redirect_to_the_canonical_host...` |
| 14 | **Scheduler Docker crash-loop.** `schedule:work` memakai `proc_open` yang dimatikan hardening PHP | Sedang (fungsional) | Log container saat redeploy | Loop `schedule:run`; tugas berupa closure tanpa sub-proses | `DeploymentConfigTest` |

**Hal yang sudah baik dan dikonfirmasi:** tidak ada SQL mentah dengan input pengguna; seluruh `{!! !!}` hanya untuk konten yang disanitasi; `$guarded`/`$fillable` mencegah mass assignment peran; ekspor CSV sudah menetralkan formula injection; lampiran aduan tidak dapat diakses lewat web; tidak ada secret di repository (dipindai pada setiap commit).

## 2. OWASP Top 10:2021

| Kategori | Kontrol | Status |
|---|---|---|
| A01 Broken Access Control | Policy per model, gate `admin`/`superadmin`, middleware per kelompok route, otorisasi penulis vs admin, lampiran privat, IDOR pada lampiran dicek | Diterapkan, teruji |
| A02 Cryptographic Failures | HTTPS + HSTS, TLS 1.2/1.3, cookie `Secure`/`HttpOnly`/`SameSite`, sesi terenkripsi, secret 2FA terenkripsi, bcrypt | Diterapkan (TLS bergantung konfigurasi server) |
| A03 Injection | Eloquent terparameter, sanitasi HTML (allow-list), CSP bernonce, email tanpa input pelapor, CSV anti-formula | Diterapkan, teruji |
| A04 Insecure Design | Model ancaman diwujudkan: whitelist login, 2FA wajib, pemisahan penyimpanan publik/privat, retensi data | Diterapkan |
| A05 Security Misconfiguration | `APP_DEBUG=false`, header keamanan, `open_basedir`, `disable_functions`, container read-only, file tersembunyi diblokir | Diterapkan, teruji |
| A06 Vulnerable Components | `composer audit`/`npm audit`, Dependabot, Trivy di CI, PHP dipatok ≥ 8.5.12 | Diterapkan (CI belum dijalankan di GitHub; lihat bagian 5) |
| A07 Identification & Authentication Failures | 2FA TOTP + kode pemulihan + anti-replay, rate limit berlapis, captcha, password 12–72, sesi idle dan absolut, anti-enumerasi | Diterapkan, teruji |
| A08 Software & Data Integrity Failures | Aset dibundel lokal (tanpa CDN), `composer.lock`/`package-lock.json`, SHA-256 lampiran | Diterapkan |
| A09 Logging & Monitoring Failures | Audit log append-only, log keamanan JSON ke file/stderr/syslog, event penolakan akses | Diterapkan; **pemantauan aktif ada di instansi** |
| A10 SSRF | Aplikasi tidak mengambil URL dari input pengguna; `allow_url_fopen` mati | Tidak ada vektor |

## 3. OWASP ASVS (bab utama)

| Bab | Kontrol utama | Status |
|---|---|---|
| V2/V6 Autentikasi | Password 12–72, cek kebocoran (produksi), MFA TOTP, throttling, anti-enumerasi | Diterapkan |
| V3/V7 Sesi | Regenerasi ID saat login, idle 30 menit, absolut 8 jam, sesi lain diakhiri, `Cache-Control: no-store` | Diterapkan |
| V4/V8 Akses | Policy + gate, deny-by-default untuk dashboard, audit penolakan | Diterapkan |
| V5 Validasi & Encoding | Validasi server-side, allow-list sanitizer, escape default Blade | Diterapkan |
| V12 File | Nama acak, validasi ekstensi **dan** isi, SVG ditolak, penyimpanan privat, unduhan `octet-stream` | Diterapkan; **belum ada pemindaian malware** |
| V13/V14 Konfigurasi | Header, CSP, hardening PHP/Nginx/Docker | Diterapkan |
| V16 Logging | Audit log + log keamanan; data pribadi disamarkan | Diterapkan |

## 4. ISO/IEC 27001:2022 Annex A

| Kontrol | Implementasi teknis | Tanggung jawab instansi |
|---|---|---|
| A.5.15 Kontrol akses | Peran Penulis/Admin/Superadmin, policy | Menetapkan siapa memegang peran, tinjau berkala |
| A.5.16/5.18 Manajemen identitas & hak akses | Akun aktif/nonaktif, pencabutan sesi, reset 2FA, `csirt:reset-admin` | Prosedur joiner/mover/leaver |
| A.5.17 Informasi autentikasi | Kebijakan password, 2FA, kode pemulihan | Sosialisasi ke pengguna |
| A.5.24–5.28 Manajemen insiden | Modul aduan bertiket, status, linimasa, bukti ber-hash | SOP penanganan, SLA |
| A.5.33 Perlindungan catatan | Audit log append-only, retensi | Menetapkan masa simpan sesuai regulasi |
| A.5.34 Privasi & PII | Minimisasi di audit log, anonimisasi aduan (`csirt:prune`) | Dasar hukum, pemberitahuan privasi, hak subjek data |
| A.8.2/8.5 Hak istimewa & autentikasi aman | Superadmin terpisah, 2FA wajib, whitelist IP login | Pengelolaan akun darurat |
| A.8.8 Kerentanan teknis | Audit dependensi, Dependabot, Trivy, patch PHP terpantau | Jadwal patch OS, pemantauan advisory |
| A.8.9 Manajemen konfigurasi | Konfigurasi sebagai kode (Docker/skrip), `.env` terpisah; pengaturan operasional dari dashboard (superadmin, diaudit); pengaturan yang bisa melemahkan keamanan hanya lewat `.env` | Kontrol perubahan |
| A.8.12 Pencegahan kebocoran data | Penyimpanan privat, TLP, tanpa isi insiden di email | Klasifikasi informasi |
| A.8.13 Backup | Backup harian terjadwal (on-premise) | **Enkripsi backup, salinan off-site, uji restore** |
| A.8.15 Logging | Audit log DB + log keamanan JSON, jam via chrony | Penyimpanan terpusat/SIEM, retensi |
| A.8.16 Pemantauan | `login_failed`, `2fa_failed`, `access_denied`, `login_ip_blocked` | Alert dan tinjauan rutin |
| A.8.20/8.21 Keamanan jaringan | UFW, fail2ban, rate limit Nginx, DB hanya localhost | Segmentasi jaringan, WAF/IDS bila ada |
| A.8.24 Kriptografi | TLS 1.2/1.3, enkripsi sesi & secret 2FA | Manajemen siklus hidup sertifikat |
| A.8.25–8.29 Pengembangan aman | CI (test, SAST, secret scan, audit dependensi), 138 test | Review kode, uji penetrasi independen |
| A.8.31 Pemisahan lingkungan | `APP_ENV`, seeder dev diblokir di produksi | Lingkungan staging terpisah |

## 5. NIST

| Referensi | Kontrol | Status |
|---|---|---|
| SP 800-63B §5.1.1 (password) | Min. 12 (di atas minimum 8), cek kebocoran, tanpa aturan kedaluwarsa paksa | Sesuai; aturan komposisi tetap diwajibkan (lebih ketat dari 800-63B) |
| SP 800-63B §5.1.4 (OTP) | TOTP RFC 6238, anti-replay, batas percobaan, kode pemulihan sekali pakai | Sesuai (AAL2 dengan password + TOTP) |
| SP 800-63B §7 (sesi) | Batas idle dan absolut, reautentikasi | Sesuai |
| SP 800-53 AC-2, AC-3, AC-7, AC-12 | Manajemen akun, penegakan akses, kunci percobaan gagal, terminasi sesi | Diterapkan |
| SP 800-53 AU-2/3/9/11/12 | Event diaudit, isi catatan, perlindungan (append-only + salinan di luar DB), retensi | Diterapkan |
| SP 800-53 SC-8, SC-13, SC-28 | Transport terenkripsi, kriptografi, data diam (sesi/secret) | Sebagian: **enkripsi disk/DB bergantung server** |
| SP 800-53 SI-2, SI-10 | Perbaikan cacat, validasi input | Diterapkan |
| SP 800-218 SSDF PW.7/PW.8/RV.1 | Review kode, pengujian, SAST/SCA, penerimaan laporan kerentanan | Diterapkan (`ci.yml`, `SECURITY.md`) |

## 6. Keterbatasan yang harus diketahui

Jujur tentang hal yang **belum** ada atau **belum terbukti**:

1. **Belum ada uji penetrasi independen maupun DAST** (mis. OWASP ZAP/Burp). Analisis ini whitebox dan otomatis; uji penetrasi tetap disarankan sebelum produksi.
2. **Pipeline CI belum pernah berjalan di GitHub.** Setiap langkahnya (test, composer/npm audit, Semgrep, shellcheck) sudah saya jalankan manual di lingkungan lokal, tetapi file `ci.yml` sendiri belum tervalidasi di runner GitHub. Gitleaks dan Trivy belum dijalankan sama sekali.
3. **Lampiran aduan tidak dipindai malware.** Mitigasinya: tipe file dibatasi, disimpan privat, diunduh sebagai `octet-stream`, dan buka hanya di sandbox. Untuk pemindaian otomatis, integrasikan ClamAV.
4. **Cek kebocoran password** (`uncompromised()`) memanggil API Have I Been Pwned dari server (k-anonymity, hanya 5 karakter awal hash terkirim). Di jaringan tertutup panggilan ini gagal diam-diam; aturan komposisi tetap berlaku.
5. **Backup belum dienkripsi oleh skrip.** Enkripsi dan salinan off-site harus ditambahkan instansi.
6. **Tidak ada WAF.** Rate limit Nginx dan fail2ban membantu, tetapi bukan pengganti WAF untuk situs berisiko tinggi.
7. **Whitelist login bergantung pada IP klien yang terbaca benar.** Di belakang proxy, `TRUSTED_PROXIES`/`set_real_ip_from` harus benar; bila keliru, whitelist bisa terlewati atau malah memblokir semua orang.
8. **Akses lewat alamat IP atau domain lain ditolak (400)** karena validasi host. Akses lewat domain `APP_URL`; alias loopback (`127.0.0.1`) dialihkan otomatis; alamat IP/domain tambahan harus didaftarkan di `CSIRT_TRUSTED_HOSTS`.
9. **ISO 27001 adalah standar sistem manajemen.** Kolom "Tanggung jawab instansi" di bagian 4 menunjukkan pekerjaan yang tidak bisa diselesaikan oleh kode.

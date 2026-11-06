<?php

$list = fn (?string $value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

return [

    /*
    | Whitelist IP halaman & proses login (/csirt-login). Mendukung IP tunggal
    | maupun CIDR, dipisah koma, mis. "127.0.0.1,10.10.0.0/16". Isi "*" untuk
    | menonaktifkan pembatasan (tidak disarankan untuk produksi).
    */
    'login_whitelist' => $list(env('CSIRT_LOGIN_WHITELIST', '127.0.0.1,::1')),

    /*
    | IP/CIDR reverse proxy yang dipercaya (header X-Forwarded-*). Isi bila
    | aplikasi berada di belakang Nginx/load balancer, mis. "172.16.0.0/12".
    */
    'trusted_proxies' => $list(env('TRUSTED_PROXIES')),

    /*
    | Host tambahan yang diterima selain host APP_URL dan loopback (localhost,
    | 127.0.0.1, ::1). Isi bila situs juga diakses lewat alamat IP atau domain alias,
    | pisahkan koma, tanpa skema/port. Contoh: "10.10.1.5,csirt.instansi.go.id".
    | Host lain mendapat 400 Bad Request (mencegah Host header poisoning).
    */
    'trusted_hosts' => $list(env('CSIRT_TRUSTED_HOSTS')),

    /*
    | Alihkan alamat alias loopback (127.0.0.1, ::1) ke host APP_URL. Cookie sesi
    | terikat ke nama host, jadi berpindah antara localhost dan 127.0.0.1 membuat
    | sesi admin tampak hilang. Matikan (false) bila perlu mengakses lewat alias.
    */
    'canonical_redirect' => (bool) env('CSIRT_CANONICAL_REDIRECT', true),

    /*
    | Wajibkan 2FA (TOTP) untuk semua akun dashboard. Pengguna yang belum
    | mengaktifkan 2FA akan diarahkan ke halaman Keamanan Akun.
    */
    'enforce_2fa' => (bool) env('CSIRT_ENFORCE_2FA', true),

    /*
    | Batas umur sesi absolut sejak login (menit), terlepas dari aktivitas.
    | Isi 0 untuk menonaktifkan (hanya mengandalkan SESSION_LIFETIME idle).
    */
    'session_absolute_minutes' => (int) env('CSIRT_SESSION_ABSOLUTE_MINUTES', 480),

    /*
    | Kanal log untuk jejak audit keamanan (lihat config/logging.php):
    |   security        : file harian storage/logs/security.log (JSON, simpan 365 hari)
    |   security_stderr : stderr (Docker; dikumpulkan oleh log driver container)
    |   syslog          : syslog lokal, untuk diteruskan ke SIEM (rsyslog/syslog-ng)
    */
    'security_log_channel' => env('CSIRT_SECURITY_LOG_CHANNEL') ?: 'security',

    /*
    | Retensi data (hari), dijalankan harian oleh `php artisan csirt:prune`.
    | Isi 0 untuk menonaktifkan masing-masing aturan.
    |   audit_log_days     : hapus audit log lebih lama dari ini
    |   closed_ticket_days : anonimkan data pelapor & hapus lampiran aduan yang
    |                        sudah ditutup lebih lama dari ini (UU PDP: data pribadi
    |                        tidak disimpan lebih lama dari yang diperlukan)
    */
    'retention' => [
        'audit_log_days' => (int) env('CSIRT_RETENTION_AUDIT_LOG_DAYS', 730),
        'closed_ticket_days' => (int) env('CSIRT_RETENTION_CLOSED_TICKET_DAYS', 0),
    ],

    /*
    | Penomoran tiket aduan & advisory.
    */
    'ticket_prefix' => env('CSIRT_TICKET_PREFIX', 'CSIRT'),
    'advisory_prefix' => env('CSIRT_ADVISORY_PREFIX', 'ADV'),

    /*
    | Notifikasi email aduan (tanda terima & pembaruan ke pelapor, pemberitahuan
    | ke tim). Default NONAKTIF: aduan dipantau dan ditindaklanjuti lewat
    | dashboard, pelapor memantau lewat halaman Cek Status. Aktifkan (true)
    | hanya setelah MAIL_* (SMTP) dikonfigurasi.
    */
    'mail_notifications' => (bool) env('CSIRT_MAIL_NOTIFICATIONS', false),

    /*
    | Email tim yang menerima notifikasi aduan baru. Hanya dipakai bila
    | mail_notifications aktif (kosongkan untuk tidak mengirim ke tim).
    */
    'team_email' => env('CSIRT_TEAM_EMAIL'),

    /*
    | Lampiran bukti pada form aduan.
    */
    'ticket_attachment' => [
        'max_files' => 5,
        'max_kb' => (int) env('CSIRT_TICKET_MAX_KB', 5120),
        'mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'log', 'csv', 'eml', 'zip'],
    ],

    /*
    | security.txt (RFC 9116). Kontak default diambil dari data Footer.
    */
    'security_txt' => [
        'contact' => env('CSIRT_SECURITY_CONTACT'),
        'policy_url' => env('CSIRT_SECURITY_POLICY_URL'),
        'languages' => env('CSIRT_SECURITY_LANGUAGES', 'id, en'),
    ],

];

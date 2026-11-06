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
    | Wajibkan 2FA (TOTP) untuk semua akun dashboard. Pengguna yang belum
    | mengaktifkan 2FA akan diarahkan ke halaman Keamanan Akun.
    */
    'enforce_2fa' => (bool) env('CSIRT_ENFORCE_2FA', true),

    /*
    | Penomoran tiket aduan & advisory.
    */
    'ticket_prefix' => env('CSIRT_TICKET_PREFIX', 'CSIRT'),
    'advisory_prefix' => env('CSIRT_ADVISORY_PREFIX', 'ADV'),

    /*
    | Email tim yang menerima notifikasi aduan baru (kosongkan untuk menonaktifkan).
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

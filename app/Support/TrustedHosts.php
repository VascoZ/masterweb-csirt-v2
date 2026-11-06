<?php

namespace App\Support;

/**
 * Daftar header Host yang diterima aplikasi (mencegah Host header poisoning, CWE-644).
 *
 * Diterima:
 *  - host dari APP_URL;
 *  - alamat loopback (localhost, 127.0.0.1, ::1): hanya menunjuk ke mesin si pemanggil
 *    sendiri sehingga tidak berbahaya, dan diperlukan untuk uji lokal & health check;
 *  - host tambahan di CSIRT_TRUSTED_HOSTS (mis. alamat IP server atau domain alias).
 */
class TrustedHosts
{
    public const LOOPBACK = ['localhost', '127.0.0.1', '[::1]', '::1'];

    /**
     * @return list<string> daftar host (huruf kecil, tanpa port)
     */
    public static function hosts(): array
    {
        $hosts = self::LOOPBACK;

        if ($appHost = parse_url((string) config('app.url'), PHP_URL_HOST)) {
            $hosts[] = $appHost;
        }

        foreach ((array) config('csirt.trusted_hosts', []) as $host) {
            $hosts[] = $host;
        }

        return array_values(array_unique(array_filter(array_map(fn ($h) => strtolower(trim((string) $h)), $hosts))));
    }

    /**
     * Pola regex untuk Symfony Request::setTrustedHosts (cocok persis, tanpa subdomain).
     *
     * @return list<string>
     */
    public static function patterns(): array
    {
        return array_map(fn ($host) => '^' . preg_quote($host, '#') . '$', self::hosts());
    }

    public static function isTrusted(string $host): bool
    {
        return in_array(strtolower($host), self::hosts(), true);
    }
}

<?php

namespace App\Support;

use App\Models\TaskRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pemeriksaan kesehatan & postur keamanan untuk halaman Pengaturan Sistem.
 * Hanya-baca: tidak ada yang diubah di sini.
 */
class SystemStatus
{
    /** Versi PHP minimum per cabang yang memuat tambalan CVE-2026-109618/109619. */
    private const PHP_MIN_PATCHED = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];

    /**
     * @return list<array{label: string, value: string, level: 'ok'|'warn'|'bad'|'info', hint: ?string}>
     */
    public static function checks(): array
    {
        return array_values(array_filter([
            self::scheduler(),
            self::session(),
            self::php(),
            self::row('Laravel', app()->version(), 'info'),
            self::database(),
            self::debug(),
            self::https(),
            self::twoFactor(),
            self::whitelist(),
            self::mail(),
            self::disk(),
            self::storage(),
            self::lastPrune(),
        ]));
    }

    private static function scheduler(): array
    {
        $age = TaskRunner::heartbeatAge();

        if ($age === null) {
            return self::row('Scheduler', 'Belum pernah berjalan', 'bad',
                'Tugas terjadwal tidak akan jalan. Docker: pastikan service "scheduler" aktif. On-premise: periksa /etc/cron.d/csirt dan service cron.');
        }

        if ($age > 180) {
            return self::row('Scheduler', 'Terakhir aktif ' . self::ago($age), 'warn', 'Scheduler berhenti atau tertunda. Periksa cron / container scheduler.');
        }

        return self::row('Scheduler', 'Aktif (detak ' . self::ago($age) . ')', 'ok');
    }

    private static function session(): array
    {
        $d = Settings::sessionDurations();
        $absolute = $d['absolute'] > 0 ? self::duration($d['absolute']) : 'tanpa batas';

        return self::row('Durasi sesi', 'Tidak aktif ' . self::duration($d['idle']) . ' · maksimum ' . $absolute . ' (skema ' . $d['profile'] . ')',
            $d['absolute'] === 0 ? 'warn' : 'info',
            $d['absolute'] === 0 ? 'Tanpa batas umur: sesi yang terus aktif tidak pernah meminta login ulang.' : null);
    }

    private static function duration(int $minutes): string
    {
        return $minutes >= 60 && $minutes % 60 === 0 ? intdiv($minutes, 60) . ' jam' : $minutes . ' menit';
    }

    private static function php(): array
    {
        $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $min = self::PHP_MIN_PATCHED[$branch] ?? null;

        if ($min && version_compare(PHP_VERSION, $min, '<')) {
            return self::row('PHP', PHP_VERSION, 'bad', "Belum memuat tambalan CVE-2026-109618/109619 (RCE kritis). Perbarui ke minimal {$min}.");
        }

        return self::row('PHP', PHP_VERSION, $min ? 'ok' : 'warn', $min ? null : 'Cabang PHP ini tidak dikenal oleh pemeriksa; pastikan masih menerima patch keamanan.');
    }

    private static function database(): array
    {
        try {
            DB::select('select 1');

            return self::row('Database', DB::getDriverName() . ' terhubung', 'ok');
        } catch (Throwable) {
            return self::row('Database', 'Tidak terhubung', 'bad');
        }
    }

    private static function debug(): array
    {
        if (config('app.debug') && app()->isProduction()) {
            return self::row('Mode debug', 'AKTIF di produksi', 'bad', 'Membocorkan detail error dan konfigurasi. Set APP_DEBUG=false di .env.');
        }

        return self::row('Mode debug', config('app.debug') ? 'Aktif (' . app()->environment() . ')' : 'Nonaktif', 'ok');
    }

    private static function https(): array
    {
        if (app()->isProduction() && ! str_starts_with((string) config('app.url'), 'https://')) {
            return self::row('HTTPS', 'APP_URL tidak memakai https://', 'warn', 'Cookie sesi dan data login berjalan tanpa enkripsi bila situs tidak dilayani HTTPS.');
        }

        return self::row('HTTPS', str_starts_with((string) config('app.url'), 'https://') ? 'Aktif' : 'Tidak (non-produksi)', 'ok');
    }

    private static function twoFactor(): array
    {
        $withoutMfa = User::where('is_active', true)->where(fn ($q) => $q->where('is_admin', true)->orWhere('is_superadmin', true))
            ->whereNull('two_factor_confirmed_at')->count();

        if (! config('csirt.enforce_2fa')) {
            return self::row('Kewajiban 2FA', 'NONAKTIF', 'bad', 'Set CSIRT_ENFORCE_2FA=true di .env.');
        }

        return self::row('Kewajiban 2FA', 'Aktif' . ($withoutMfa ? " ({$withoutMfa} admin belum mengaktifkan)" : ''), $withoutMfa ? 'warn' : 'ok',
            $withoutMfa ? 'Mereka akan dipaksa mengaktifkan 2FA saat login berikutnya.' : null);
    }

    private static function whitelist(): array
    {
        $list = (array) config('csirt.login_whitelist');

        if (in_array('*', $list, true)) {
            return self::row('Whitelist IP login', 'Terbuka untuk semua IP (*)', 'bad', 'Halaman login dapat dicoba dari internet. Batasi lewat CSIRT_LOGIN_WHITELIST di .env.');
        }

        return self::row('Whitelist IP login', count($list) . ' entri: ' . implode(', ', array_slice($list, 0, 4)) . (count($list) > 4 ? ', …' : ''), 'info',
            'Hanya dapat diubah lewat .env di server (mencegah terkunci dari dashboard).');
    }

    private static function mail(): array
    {
        if (! config('csirt.mail_notifications')) {
            return self::row('Notifikasi email', 'Nonaktif', 'info');
        }

        if (config('mail.default') === 'log') {
            return self::row('Notifikasi email', 'Aktif, tetapi MAIL_MAILER=log', 'warn', 'Email hanya ditulis ke log, tidak terkirim. Isi MAIL_* (SMTP) di .env.');
        }

        return self::row('Notifikasi email', 'Aktif (' . config('mail.default') . ')', 'ok');
    }

    private static function disk(): ?array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if (! $free || ! $total) {
            return null;
        }

        $percent = (int) round($free / $total * 100);

        return self::row('Ruang disk', "{$percent}% bebas (" . number_format($free / 1024 ** 3, 1) . ' GB)', $percent < 10 ? 'bad' : ($percent < 20 ? 'warn' : 'ok'));
    }

    private static function storage(): array
    {
        $ok = is_writable(storage_path('app/private')) && is_writable(storage_path('app/public'));

        return self::row('Penyimpanan', $ok ? 'Dapat ditulis' : 'Tidak dapat ditulis', $ok ? 'ok' : 'bad', $ok ? null : 'Unggahan dan bukti aduan akan gagal disimpan. Periksa izin storage/.');
    }

    private static function lastPrune(): ?array
    {
        $run = TaskRun::where('task', 'prune')->where('dry_run', false)->latest('started_at')->first();

        if (! $run) {
            return self::row('Retensi data', 'Belum pernah dijalankan', 'info');
        }

        return self::row('Retensi data', ($run->succeeded() ? 'Berhasil ' : 'GAGAL ') . $run->started_at->diffForHumans(), $run->succeeded() ? 'ok' : 'bad');
    }

    private static function row(string $label, string $value, string $level, ?string $hint = null): array
    {
        return compact('label', 'value', 'level', 'hint');
    }

    private static function ago(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => "{$seconds} detik lalu",
            $seconds < 3600 => intdiv($seconds, 60) . ' menit lalu',
            default => intdiv($seconds, 3600) . ' jam lalu',
        };
    }
}

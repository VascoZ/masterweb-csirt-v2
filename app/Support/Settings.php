<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pengaturan runtime yang bisa diubah superadmin dari dashboard.
 *
 * Prinsip keamanan:
 *  - Hanya kunci yang terdaftar di DEFINITIONS yang dapat dibaca/ditulis (allow-list).
 *  - Pengaturan yang bisa mengunci semua pengguna atau melemahkan keamanan
 *    (whitelist login, kewajiban 2FA, proxy tepercaya, host tepercaya) SENGAJA
 *    tidak ada di sini: itu hanya bisa diubah lewat .env di server.
 *  - Nilai disimpan di database dan menimpa default dari .env/config saat boot.
 */
class Settings
{
    private const CACHE_KEY = 'csirt.settings';

    /**
     * type   : bool | int | email | time
     * config : kunci config yang ditimpa nilai ini (opsional)
     * rules  : aturan validasi Laravel
     */
    public const DEFINITIONS = [
        'tickets_enabled' => [
            'group' => 'fitur', 'type' => 'bool', 'default' => true,
            'label' => 'Terima aduan baru',
            'help' => 'Matikan sementara saat pemeliharaan atau ketika form aduan sedang disalahgunakan. Cek status aduan lama tetap berfungsi.',
            'rules' => ['boolean'],
        ],
        'mail_notifications' => [
            'group' => 'notifikasi', 'type' => 'bool', 'config' => 'csirt.mail_notifications',
            'label' => 'Kirim notifikasi email aduan',
            'help' => 'Butuh SMTP yang sudah dikonfigurasi di .env (MAIL_*). Isi aduan tidak pernah dikirim lewat email.',
            'rules' => ['boolean'],
        ],
        'team_email' => [
            'group' => 'notifikasi', 'type' => 'email', 'config' => 'csirt.team_email', 'nullable' => true,
            'label' => 'Email tim penerima aduan baru',
            'help' => 'Hanya dipakai bila notifikasi email aktif. Kosongkan untuk tidak mengirim ke tim.',
            'rules' => ['nullable', 'email', 'max:255'],
        ],
        'session_profile' => [
            'group' => 'keamanan', 'type' => 'select', 'default' => 'bawaan',
            'options' => [
                'bawaan' => 'Ikuti .env (SESSION_LIFETIME & CSIRT_SESSION_ABSOLUTE_MINUTES)',
                'ketat' => 'Ketat: tidak aktif 20 menit, maksimum 4 jam',
                'standar' => 'Standar: tidak aktif 30 menit, maksimum 8 jam',
                'longgar' => 'Longgar: tidak aktif 2 jam, maksimum 24 jam',
                'kustom' => 'Kustom: atur sendiri di bawah',
            ],
            'label' => 'Skema durasi sesi',
            'help' => 'Berlaku untuk semua sesi, termasuk pengunjung situs publik (mis. pengisi form aduan yang diam lama bisa perlu memuat ulang). Aktivitas di mana pun, termasuk membuka situs utama, memperpanjang batas "tidak aktif".',
            'rules' => [],
        ],
        'session_idle_minutes' => [
            'group' => 'keamanan', 'type' => 'int', 'config' => 'session.lifetime',
            'label' => 'Kustom: batas tidak aktif (menit)',
            'help' => 'Sesi berakhir bila tidak ada aktivitas selama ini. Hanya dipakai bila skema = Kustom. Rentang 5–1440.',
            'rules' => ['integer', 'min:5', 'max:1440'],
        ],
        'session_absolute_minutes' => [
            'group' => 'keamanan', 'type' => 'int', 'config' => 'csirt.session_absolute_minutes',
            'label' => 'Kustom: batas umur sesi dashboard (menit)',
            'help' => 'Sesi berakhir sejak login walau pengguna aktif. 0 = tanpa batas. Hanya dipakai bila skema = Kustom. Minimal sama dengan batas tidak aktif.',
            'rules' => ['integer', 'min:0', 'max:10080'],
        ],
        'retention_audit_log_days' => [
            'group' => 'retensi', 'type' => 'int', 'config' => 'csirt.retention.audit_log_days',
            'label' => 'Simpan audit log (hari)',
            'help' => 'Audit log lebih lama dihapus oleh tugas Retensi data. 0 = simpan selamanya.',
            'rules' => ['integer', 'min:0', 'max:3650'],
        ],
        'retention_closed_ticket_days' => [
            'group' => 'retensi', 'type' => 'int', 'config' => 'csirt.retention.closed_ticket_days',
            'label' => 'Anonimkan aduan yang sudah ditutup setelah (hari)',
            'help' => 'Data pribadi pelapor, kronologi, catatan, dan lampiran dihapus permanen; nomor tiket, kategori, dan status tetap disimpan. 0 = tidak pernah.',
            'rules' => ['integer', 'min:0', 'max:3650'],
        ],
        'task_prune_enabled' => [
            'group' => 'tugas', 'type' => 'bool', 'default' => true,
            'label' => 'Aktifkan tugas Retensi data',
            'help' => 'Menjalankan kebijakan retensi di atas setiap hari.',
            'rules' => ['boolean'],
        ],
        'task_prune_time' => [
            'group' => 'tugas', 'type' => 'time', 'default' => '03:30',
            'label' => 'Jam eksekusi Retensi data (WIB)',
            'help' => 'Format 24 jam, mis. 03:30. Gunakan jam sepi.',
            'rules' => ['date_format:H:i'],
        ],
    ];

    /** Skema durasi sesi (menit): batas tidak aktif dan batas umur absolut. */
    public const SESSION_PROFILES = [
        'ketat' => ['idle' => 20, 'absolute' => 240],
        'standar' => ['idle' => 30, 'absolute' => 480],
        'longgar' => ['idle' => 120, 'absolute' => 1440],
    ];

    /** Kunci durasi yang hanya berlaku pada skema "kustom" (diatur oleh applySession). */
    private const SESSION_KEYS = ['session_idle_minutes', 'session_absolute_minutes'];

    /** @var array<string, mixed>|null */
    private static ?array $memo = null;

    /**
     * Nilai tersimpan (override), tanpa default. Aman dipanggil sebelum tabel ada
     * (mis. saat migrasi pertama): bila gagal, hasilnya kosong dan tidak di-cache.
     *
     * @return array<string, string|null>
     */
    public static function stored(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            $stored = Cache::get(self::CACHE_KEY);

            if (! is_array($stored)) {
                $stored = DB::table('settings')->pluck('value', 'key')->all();
                Cache::forever(self::CACHE_KEY, $stored);
            }

            return self::$memo = $stored;
        } catch (Throwable) {
            return [];
        }
    }

    public static function get(string $key): mixed
    {
        $definition = self::DEFINITIONS[$key] ?? throw new \InvalidArgumentException("Pengaturan tidak dikenal: {$key}");
        $stored = self::stored();

        if (array_key_exists($key, $stored)) {
            return self::cast($definition, $stored[$key]);
        }

        // Tanpa override: pakai config (.env) bila ada, jika tidak default registry.
        if (isset($definition['config'])) {
            return config($definition['config']);
        }

        return $definition['default'] ?? null;
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    /**
     * Simpan beberapa pengaturan sekaligus. Hanya kunci terdaftar; nilai sudah
     * divalidasi pemanggil (lihat rules()).
     *
     * @param  array<string, mixed>  $values
     * @return array{old: array<string, mixed>, new: array<string, mixed>} perubahan saja
     */
    public static function save(array $values, ?int $userId): array
    {
        $old = $new = [];

        foreach ($values as $key => $value) {
            if (! isset(self::DEFINITIONS[$key])) {
                continue;
            }

            $current = self::get($key);
            $normalized = self::normalize(self::DEFINITIONS[$key], $value);

            if ($current === $normalized) {
                continue;
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => self::serialize($normalized), 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()],
            );
            $old[$key] = $current;
            $new[$key] = $normalized;
        }

        self::flush();

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Terapkan override ke config runtime. Dipanggil sekali saat boot.
     */
    public static function applyOverrides(): void
    {
        foreach (self::DEFINITIONS as $key => $definition) {
            if (in_array($key, self::SESSION_KEYS, true)) {
                continue; // diatur oleh skema, lihat applySession()
            }
            if (isset($definition['config']) && array_key_exists($key, self::stored())) {
                config([$definition['config'] => self::get($key)]);
            }
        }

        self::applySession();
    }

    /**
     * Terapkan skema durasi sesi: preset menentukan kedua batas; "kustom" memakai nilai
     * tersimpan; "bawaan" membiarkan nilai dari .env.
     */
    private static function applySession(): void
    {
        $profile = (string) self::get('session_profile');

        if (isset(self::SESSION_PROFILES[$profile])) {
            config([
                'session.lifetime' => self::SESSION_PROFILES[$profile]['idle'],
                'csirt.session_absolute_minutes' => self::SESSION_PROFILES[$profile]['absolute'],
            ]);
        } elseif ($profile === 'kustom') {
            foreach (self::SESSION_KEYS as $key) {
                if (array_key_exists($key, self::stored())) {
                    config([self::DEFINITIONS[$key]['config'] => self::get($key)]);
                }
            }
        }
    }

    /**
     * Durasi sesi yang berlaku saat ini (setelah skema diterapkan), untuk ditampilkan.
     *
     * @return array{profile: string, idle: int, absolute: int}
     */
    public static function sessionDurations(): array
    {
        return [
            'profile' => (string) self::get('session_profile'),
            'idle' => (int) config('session.lifetime'),
            'absolute' => (int) config('csirt.session_absolute_minutes'),
        ];
    }

    /**
     * Aturan validasi untuk form pengaturan.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        $rules = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            // Checkbox dikirim sebagai "0"/"1" (lihat x-form.checkbox), jadi semua kunci selalu ada.
            $rules[$key] = ($definition['nullable'] ?? false) ? $definition['rules'] : ['required', ...$definition['rules']];

            if ($definition['type'] === 'select') {
                $rules[$key][] = 'in:' . implode(',', array_keys($definition['options']));
            }
        }

        // Batas umur sesi: 0 (tanpa batas) atau tidak lebih pendek dari batas tidak aktif.
        $rules['session_absolute_minutes'][] = fn ($attr, $value, $fail) => ((int) $value > 0 && (int) $value < (int) request()->input('session_idle_minutes'))
            ? $fail('Batas umur sesi tidak boleh lebih pendek dari batas tidak aktif (atau isi 0 untuk tanpa batas).')
            : null;

        return $rules;
    }

    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    private static function cast(array $definition, ?string $raw): mixed
    {
        return match ($definition['type']) {
            'bool' => $raw === '1',
            'int' => (int) $raw,
            default => $raw === '' ? null : $raw,
        };
    }

    private static function normalize(array $definition, mixed $value): mixed
    {
        return match ($definition['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            default => blank($value) ? null : (string) $value,
        };
    }

    private static function serialize(mixed $value): ?string
    {
        return is_bool($value) ? ($value ? '1' : '0') : ($value === null ? null : (string) $value);
    }
}

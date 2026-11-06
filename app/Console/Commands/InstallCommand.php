<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Footer;
use App\Models\Profil;
use App\Models\Service;
use App\Models\User;
use App\Support\SiteData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Instalasi awal situs CSIRT: migrasi database, storage link,
 * akun superadmin pertama, dan konten awal. Aman dijalankan berulang.
 */
class InstallCommand extends Command
{
    protected $signature = 'csirt:install
        {--site-name= : Nama tim CSIRT, mis. "Contoh-CSIRT"}
        {--admin-name= : Nama superadmin}
        {--admin-username= : Username superadmin}
        {--admin-email= : Email superadmin}
        {--admin-password= : Password superadmin (disarankan lewat env ADMIN_PASSWORD)}
        {--skip-migrate : Lewati migrasi database}';

    protected $description = 'Instalasi awal situs CSIRT (migrasi, superadmin, konten awal)';

    public function handle(): int
    {
        $this->components->info('Instalasi Situs CSIRT');

        if (blank(config('app.key'))) {
            $this->call('key:generate', ['--force' => true]);
        }

        if (! $this->option('skip-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        if (! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        $this->createSuperadmin();
        $this->seedInitialContent();

        SiteData::flush();
        $this->checkConfiguration();

        $this->components->info('Instalasi selesai. Login melalui ' . route('login'));

        return self::SUCCESS;
    }

    private function createSuperadmin(): void
    {
        if (User::where('is_superadmin', true)->exists()) {
            $this->components->twoColumnDetail('Superadmin', '<fg=yellow>sudah ada, dilewati</>');

            return;
        }

        $data = [
            'name' => $this->value('admin-name', 'ADMIN_NAME', fn () => text('Nama superadmin', default: 'Administrator', required: true)),
            'username' => $this->value('admin-username', 'ADMIN_USERNAME', fn () => text('Username superadmin', default: 'admin', required: true)),
            'email' => $this->value('admin-email', 'ADMIN_EMAIL', fn () => text('Email superadmin', required: true)),
            'password' => $this->value('admin-password', 'ADMIN_PASSWORD', fn () => password('Password superadmin', required: true,
                hint: 'Min. 12 karakter, huruf besar & kecil, angka, simbol')),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            throw new \RuntimeException('Data superadmin tidak valid.');
        }

        $user = new User($data);
        $user->is_admin = true;
        $user->is_superadmin = true;
        $user->email_verified_at = now();
        $user->save();

        $this->components->twoColumnDetail('Superadmin', "<fg=green>{$user->email} dibuat</>");
    }

    private function seedInitialContent(): void
    {
        $siteName = $this->option('site-name') ?: env('CSIRT_SITE_NAME');

        if (Profil::doesntExist()) {
            $siteName ??= $this->input->isInteractive() ? text('Nama tim CSIRT', default: 'Contoh-CSIRT') : 'Contoh-CSIRT';
            Profil::create([
                'name' => $siteName,
                'slug' => Str::slug($siteName),
                'content' => '<div>' . e($siteName) . ' adalah Tim Tanggap Insiden Siber yang bertugas menerima, menganalisis, dan menanggapi insiden siber di lingkungan instansi. Ubah teks ini melalui Dashboard &rarr; Profil.</div>',
            ]);
            $this->components->twoColumnDetail('Profil awal', '<fg=green>dibuat</>');
        }

        if (Service::doesntExist()) {
            Service::create([
                'name' => 'Layanan Utama',
                'slug' => 'layanan-utama',
                'content' => '<ul><li>Penanggulangan dan pemulihan insiden siber</li><li>Pemberian peringatan terkait keamanan siber</li><li>Penanganan kerentanan sistem elektronik</li></ul>',
            ]);
            $this->components->twoColumnDetail('Layanan awal', '<fg=green>dibuat</>');
        }

        if (Category::doesntExist()) {
            foreach (['Berita', 'Edukasi Keamanan Siber', 'Kegiatan'] as $name) {
                Category::create(['name' => $name, 'slug' => Str::slug($name)]);
            }
            $this->components->twoColumnDetail('Kategori artikel awal', '<fg=green>dibuat</>');
        }

        if (Footer::doesntExist()) {
            $this->components->warn('Data kontak belum ada. Lengkapi melalui Dashboard → Kontak & Peta.');
        }
    }

    private function checkConfiguration(): void
    {
        $warnings = array_filter([
            app()->isProduction() && config('app.debug') ? 'APP_DEBUG=true di produksi. Ubah menjadi false.' : null,
            in_array('*', config('csirt.login_whitelist'), true) ? 'CSIRT_LOGIN_WHITELIST="*": halaman login terbuka untuk semua IP.' : null,
            ! config('csirt.enforce_2fa') ? 'CSIRT_ENFORCE_2FA=false: 2FA tidak diwajibkan.' : null,
            app()->isProduction() && ! str_starts_with((string) config('app.url'), 'https://') ? 'APP_URL tidak memakai https://.' : null,
            config('csirt.mail_notifications') && config('mail.default') === 'log' ? 'CSIRT_MAIL_NOTIFICATIONS=true tetapi MAIL_MAILER=log: email aduan hanya ditulis ke log.' : null,
        ]);

        if (! config('csirt.mail_notifications')) {
            $this->components->info('Notifikasi email aduan nonaktif: aduan dikelola melalui Dashboard → Aduan. Aktifkan dengan CSIRT_MAIL_NOTIFICATIONS=true setelah SMTP siap.');
        }

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }
    }

    private function value(string $option, string $env, callable $prompt): string
    {
        $value = $this->option($option) ?: env($env);

        if (filled($value)) {
            return (string) $value;
        }

        if (! $this->input->isInteractive()) {
            throw new \RuntimeException("Isi opsi --{$option} atau variabel {$env} untuk mode non-interaktif.");
        }

        return $prompt();
    }
}

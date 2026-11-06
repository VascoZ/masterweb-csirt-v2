<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Sessions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

/**
 * Memulihkan akses superadmin dari server, mis. setelah migrasi data 2022
 * (password akun lama tidak diketahui) atau bila aplikasi autentikator hilang.
 */
class ResetAdminCommand extends Command
{
    protected $signature = 'csirt:reset-admin
        {email : Email akun yang akan dipulihkan}
        {--password= : Password baru (disarankan lewat prompt atau env ADMIN_PASSWORD)}
        {--new-email= : Ganti email akun sekaligus}
        {--keep-2fa : Jangan reset 2FA}';

    protected $description = 'Atur ulang password (dan 2FA) akun lalu jadikan superadmin';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('Akun ' . $this->argument('email') . ' tidak ditemukan. Akun yang ada:');
            $this->table(['Email', 'Nama', 'Peran'], User::orderBy('email')->get()->map(fn ($u) => [$u->email, $u->name, $u->roleLabel()]));

            return self::FAILURE;
        }

        $plain = $this->option('password') ?: env('ADMIN_PASSWORD');
        if (blank($plain)) {
            if (! $this->input->isInteractive()) {
                $this->components->error('Isi --password atau env ADMIN_PASSWORD untuk mode non-interaktif.');

                return self::FAILURE;
            }
            $plain = password('Password baru', required: true, hint: 'Min. 12 karakter, huruf besar & kecil, angka, simbol');
        }

        $data = ['password' => $plain, 'email' => $this->option('new-email') ?: $user->email];
        $validator = Validator::make($data, [
            'password' => ['required', Password::defaults()],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user->email = $data['email'];
        $user->password = $plain;
        $user->is_admin = true;
        $user->is_superadmin = true;
        $user->is_active = true;

        if (! $this->option('keep-2fa')) {
            $user->resetTwoFactor();
        }

        $user->save();
        Sessions::terminateAll($user);
        AuditLogger::log('admin_reset', $user, description: 'Akses superadmin dipulihkan lewat konsol (csirt:reset-admin)');

        $this->components->info("Akun {$user->email} sekarang superadmin dengan password baru.");
        if (! $this->option('keep-2fa')) {
            $this->components->warn('2FA direset: aktifkan ulang saat login berikutnya.');
        }

        return self::SUCCESS;
    }
}

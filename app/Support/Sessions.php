<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class Sessions
{
    /**
     * Akhiri sesi pengguna di perangkat lain (hanya untuk SESSION_DRIVER=database).
     * Menggantikan Auth::logoutOtherDevices() yang membutuhkan password mentah.
     */
    public static function logoutOtherDevices(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', session()->getId())
            ->delete();
    }

    /**
     * Akhiri seluruh sesi pengguna (mis. saat akun dinonaktifkan atau 2FA direset).
     */
    public static function terminateAll(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }
}

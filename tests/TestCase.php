<?php

namespace Tests;

use App\Models\User;
use App\Support\SiteData;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        SiteData::flush();
    }

    /**
     * Pengguna dashboard dengan 2FA sudah aktif (karena 2FA diwajibkan).
     */
    protected function user(string $role = 'writer', array $attributes = []): User
    {
        $factory = User::factory();
        $factory = match ($role) {
            'admin' => $factory->admin(),
            'superadmin' => $factory->superadmin(),
            default => $factory,
        };

        $user = $factory->create($attributes);
        $user->forceFill([
            'two_factor_secret' => (new Google2FA())->generateSecretKey(32),
            'two_factor_recovery_codes' => ['AAAAA-BBBBB'],
            'two_factor_confirmed_at' => now(),
        ])->saveQuietly();

        return $user;
    }
}

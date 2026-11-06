<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_creates_superadmin_and_initial_content(): void
    {
        $this->artisan('csirt:install', [
            '--no-interaction' => true,
            '--skip-migrate' => true,
            '--site-name' => 'Kominfo-CSIRT',
            '--admin-name' => 'Admin',
            '--admin-username' => 'admin',
            '--admin-email' => 'admin@kominfo.test',
            '--admin-password' => 'Kuat!2345x',
        ])->assertSuccessful();

        $admin = User::where('email', 'admin@kominfo.test')->firstOrFail();
        $this->assertTrue($admin->isSuperadmin());
        $this->assertSame('Kominfo-CSIRT', Profil::firstOrFail()->name);
        $this->assertGreaterThan(0, Category::count());

        // Dijalankan ulang: tidak membuat superadmin kedua.
        $this->artisan('csirt:install', ['--no-interaction' => true, '--skip-migrate' => true])->assertSuccessful();
        $this->assertSame(1, User::where('is_superadmin', true)->count());
    }

    public function test_install_rejects_weak_password(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->artisan('csirt:install', [
            '--no-interaction' => true, '--skip-migrate' => true,
            '--admin-name' => 'Admin', '--admin-username' => 'admin', '--admin-email' => 'admin@kominfo.test', '--admin-password' => 'lemah',
        ]);
    }
}

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

    public function test_reset_admin_restores_access_and_resets_2fa(): void
    {
        $user = $this->user('admin', ['email' => 'admin@gmail.com']);

        $this->artisan('csirt:reset-admin', [
            'email' => 'admin@gmail.com', '--password' => 'Baru!2345x', '--new-email' => 'admin@instansi.go.id', '--no-interaction' => true,
        ])->assertSuccessful();

        $user->refresh();
        $this->assertSame('admin@instansi.go.id', $user->email);
        $this->assertTrue($user->isSuperadmin());
        $this->assertTrue(\Hash::check('Baru!2345x', $user->password));
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin_reset', 'auditable_id' => $user->id]);
    }

    public function test_reset_admin_rejects_unknown_account_and_weak_password(): void
    {
        $this->user('admin', ['email' => 'ada@instansi.go.id']);

        $this->artisan('csirt:reset-admin', ['email' => 'tidak-ada@instansi.go.id', '--password' => 'Baru!2345x', '--no-interaction' => true])
            ->assertFailed();
        $this->artisan('csirt:reset-admin', ['email' => 'ada@instansi.go.id', '--password' => 'lemah', '--no-interaction' => true])
            ->assertFailed();
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

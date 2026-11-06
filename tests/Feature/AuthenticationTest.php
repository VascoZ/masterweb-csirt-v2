<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_hidden_from_non_whitelisted_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/csirt-login')
            ->assertNotFound();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post('/login', ['email' => 'a@b.c', 'password' => 'x', 'captcha' => 'x'])
            ->assertNotFound();
    }

    public function test_whitelist_supports_cidr(): void
    {
        config(['csirt.login_whitelist' => ['10.10.0.0/16']]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.5.7'])->get('/csirt-login')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '10.11.0.1'])->get('/csirt-login')->assertNotFound();
    }

    public function test_wrong_password_shows_error_and_is_audited(): void
    {
        $user = $this->user();

        $this->from('/csirt-login')
            ->post('/login', ['email' => $user->email, 'password' => 'salah', 'captcha' => 'x'])
            ->assertRedirect('/csirt-login')
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed']);
    }

    public function test_validation_error_returns_to_form_not_captcha_image(): void
    {
        // Browser memuat gambar captcha setelah halaman login -> jadi "URL sebelumnya".
        $this->get('/csirt-login');
        $this->get('/captcha/reload');

        $this->post('/login', ['email' => 'bukan-email', 'password' => 'x', 'captcha' => 'x'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->user();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah', 'captcha' => 'x']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'salah', 'captcha' => 'x'])->assertStatus(429);
    }

    public function test_user_with_2fa_must_pass_challenge(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'Password!123', 'captcha' => 'x'])
            ->assertRedirect(route('two-factor.create'));
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHas('error');
        $this->assertGuest();

        $code = (new Google2FA())->getCurrentOtp($user->two_factor_secret);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_totp_code_cannot_be_replayed(): void
    {
        $user = $this->user();
        $code = (new Google2FA())->getCurrentOtp($user->two_factor_secret);

        $this->post('/login', ['email' => $user->email, 'password' => 'Password!123', 'captcha' => 'x']);
        $this->post('/two-factor-challenge', ['code' => $code]);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'Password!123', 'captcha' => 'x']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => 'Password!123', 'captcha' => 'x']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'aaaaa-bbbbb'])->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_two_factor_challenge_expires(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => 'Password!123', 'captcha' => 'x']);

        $this->travel(6)->minutes();
        $code = (new Google2FA())->getCurrentOtp($user->two_factor_secret);

        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_without_2fa_is_forced_to_enable_it(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('dashboard.security.edit'));
        $this->actingAs($user)->get('/dashboard/security')->assertOk()->assertSee('Aktifkan 2FA');
    }

    public function test_enabling_2fa_flow(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/dashboard/security/two-factor')->assertRedirect();
        $secret = session('2fa.pending_secret');
        $this->assertNotNull($secret);

        $this->actingAs($user)->get('/dashboard/security')->assertSee('<svg', false);

        $this->actingAs($user)->post('/dashboard/security/two-factor/confirm', ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertCount(8, $user->two_factor_recovery_codes);
        // Secret tersimpan terenkripsi di database.
        $this->assertStringNotContainsString($secret, (string) \DB::table('users')->where('id', $user->id)->value('two_factor_secret'));
        $this->assertDatabaseHas('audit_logs', ['event' => '2fa_enabled', 'user_id' => $user->id]);
    }

    public function test_password_change_requires_current_password_and_strong_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/dashboard/security/password', ['current_password' => 'salah', 'password' => 'Baru!234567x', 'password_confirmation' => 'Baru!234567x'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/dashboard/security/password', ['current_password' => 'Password!123', 'password' => 'lemah', 'password_confirmation' => 'lemah'])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)->put('/dashboard/security/password', ['current_password' => 'Password!123', 'password' => 'Baru!234567x', 'password_confirmation' => 'Baru!234567x'])
            ->assertSessionHas('success');

        $this->assertTrue(\Hash::check('Baru!234567x', $user->fresh()->password));
        $this->assertTrue(AuditLog::where('event', 'password_changed')->exists());
    }

    public function test_inactive_user_cannot_login_or_keep_active_session(): void
    {
        $user = $this->user('writer', ['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Password!123', 'captcha' => 'x'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
        $this->assertGuest();

        $activeUser = $this->user();
        $this->actingAs($activeUser)->get('/dashboard')->assertOk();

        $activeUser->forceFill(['is_active' => false])->save();
        $this->actingAs($activeUser)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_absolute_session_lifetime_forces_reauthentication(): void
    {
        config(['csirt.session_absolute_minutes' => 60]);
        $user = $this->user();

        $this->actingAs($user)->withSession(['auth.started_at' => now()->timestamp])->get('/dashboard')->assertOk();

        $this->travel(61)->minutes();
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'session_expired', 'user_id' => $user->id]);
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}

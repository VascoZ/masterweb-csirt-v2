<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TaskRun;
use App\Models\Ticket;
use App\Support\Settings;
use App\Support\TaskRunner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'tickets_enabled' => '1', 'mail_notifications' => '0', 'team_email' => '',
            'session_profile' => 'bawaan', 'session_idle_minutes' => '30', 'session_absolute_minutes' => '480', 'retention_audit_log_days' => '730',
            'retention_closed_ticket_days' => '0', 'task_prune_enabled' => '1', 'task_prune_time' => '03:30',
        ];
    }

    public function test_only_superadmin_can_open_or_change_system_settings(): void
    {
        foreach (['writer', 'admin'] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->get('/dashboard/system')->assertForbidden();
            $this->actingAs($user)->put('/dashboard/system/settings', $this->form())->assertForbidden();
            $this->actingAs($user)->post('/dashboard/system/tasks/prune/run')->assertForbidden();
        }

        $this->actingAs($this->user('superadmin'))->get('/dashboard/system')
            ->assertOk()
            ->assertSee('Status sistem')
            ->assertSee('Retensi data');
    }

    public function test_settings_are_saved_audited_and_override_env_defaults(): void
    {
        $super = $this->user('superadmin');

        $this->actingAs($super)->put('/dashboard/system/settings', $this->form([
            'retention_audit_log_days' => '90', 'mail_notifications' => '1', 'team_email' => 'tim@instansi.go.id', 'task_prune_time' => '01:15',
        ]))->assertSessionHas('success');

        $this->assertSame('90', DB::table('settings')->where('key', 'retention_audit_log_days')->value('value'));

        // Simulasi boot berikutnya: override menimpa nilai config dari .env.
        Settings::flush();
        config(['csirt.retention.audit_log_days' => 730, 'csirt.mail_notifications' => false]);
        Settings::applyOverrides();

        $this->assertSame(90, config('csirt.retention.audit_log_days'));
        $this->assertTrue(config('csirt.mail_notifications'));
        $this->assertSame('tim@instansi.go.id', config('csirt.team_email'));
        $this->assertSame('01:15', Settings::get('task_prune_time'));

        $log = AuditLog::where('event', 'settings_updated')->firstOrFail();
        $this->assertSame($super->id, $log->user_id);
        $this->assertSame(90, $log->new_values['retention_audit_log_days']);
        $this->assertSame(730, $log->old_values['retention_audit_log_days']);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $super = $this->user('superadmin');

        foreach ([
            ['session_absolute_minutes', '5'], ['session_absolute_minutes', '99999'], ['session_idle_minutes', '1'],
            ['retention_audit_log_days', '-1'], ['retention_closed_ticket_days', 'abc'],
            ['task_prune_time', '25:99'], ['task_prune_time', 'malam'], ['team_email', 'bukan-email'],
        ] as [$field, $value]) {
            $this->actingAs($super)->put('/dashboard/system/settings', $this->form([$field => $value]))
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_session_lifetime_zero_disables_the_limit(): void
    {
        $this->actingAs($this->user('superadmin'))
            ->put('/dashboard/system/settings', $this->form(['session_absolute_minutes' => '0']))
            ->assertSessionHasNoErrors();
    }

    public function test_session_duration_schemes_apply_to_config(): void
    {
        $super = $this->user('superadmin');
        config(['session.lifetime' => 120, 'csirt.session_absolute_minutes' => 999]);

        // Skema preset menentukan kedua batas, mengabaikan angka kustom.
        $this->actingAs($super)->put('/dashboard/system/settings', $this->form(['session_profile' => 'ketat', 'session_idle_minutes' => '90', 'session_absolute_minutes' => '900']))
            ->assertSessionHasNoErrors();
        Settings::flush();
        Settings::applyOverrides();
        $this->assertSame(['profile' => 'ketat', 'idle' => 20, 'absolute' => 240], Settings::sessionDurations());
        $this->assertSame(20, config('session.lifetime'));

        // Kustom memakai angka tersimpan.
        $this->actingAs($super)->put('/dashboard/system/settings', $this->form(['session_profile' => 'kustom', 'session_idle_minutes' => '45', 'session_absolute_minutes' => '600']));
        Settings::flush();
        Settings::applyOverrides();
        $this->assertSame(['profile' => 'kustom', 'idle' => 45, 'absolute' => 600], Settings::sessionDurations());

        // Longgar.
        $this->actingAs($super)->put('/dashboard/system/settings', $this->form(['session_profile' => 'longgar']));
        Settings::flush();
        Settings::applyOverrides();
        $this->assertSame(120, config('session.lifetime'));
        $this->assertSame(1440, config('csirt.session_absolute_minutes'));
    }

    public function test_session_scheme_keeps_env_values_when_set_to_default(): void
    {
        config(['session.lifetime' => 77, 'csirt.session_absolute_minutes' => 333]);

        $this->actingAs($this->user('superadmin'))->put('/dashboard/system/settings', $this->form(['session_profile' => 'bawaan', 'session_idle_minutes' => '10']));
        Settings::flush();
        Settings::applyOverrides();

        $this->assertSame(77, config('session.lifetime'));
        $this->assertSame(333, config('csirt.session_absolute_minutes'));
    }

    public function test_session_scheme_validation(): void
    {
        $super = $this->user('superadmin');

        $this->actingAs($super)->put('/dashboard/system/settings', $this->form(['session_profile' => 'selamanya']))->assertSessionHasErrors('session_profile');
        $this->actingAs($super)->put('/dashboard/system/settings', $this->form(['session_idle_minutes' => '2']))->assertSessionHasErrors('session_idle_minutes');
        // Batas umur lebih pendek dari batas tidak aktif tidak masuk akal.
        $this->actingAs($super)->put('/dashboard/system/settings', $this->form(['session_idle_minutes' => '60', 'session_absolute_minutes' => '30']))->assertSessionHasErrors('session_absolute_minutes');
    }

    public function test_security_critical_settings_cannot_be_changed_from_the_web(): void
    {
        $this->actingAs($this->user('superadmin'))->put('/dashboard/system/settings', $this->form([
            'csirt_enforce_2fa' => '0', 'enforce_2fa' => '0', 'login_whitelist' => '*', 'trusted_hosts' => 'evil.example',
            'csirt.enforce_2fa' => '0',
        ]));

        $this->assertDatabaseMissing('settings', ['key' => 'login_whitelist']);
        $this->assertDatabaseMissing('settings', ['key' => 'enforce_2fa']);
        $this->assertTrue(config('csirt.enforce_2fa'));
        $this->assertNotContains('*', config('csirt.login_whitelist'));
        $this->assertNotContains('evil.example', config('csirt.trusted_hosts'));
        $this->assertArrayNotHasKey('login_whitelist', Settings::DEFINITIONS);
        $this->assertArrayNotHasKey('enforce_2fa', Settings::DEFINITIONS);
    }

    public function test_unknown_setting_key_cannot_be_read(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Settings::get('app_key');
    }

    public function test_closing_ticket_intake_blocks_new_reports_but_not_status_checks(): void
    {
        $this->actingAs($this->user('superadmin'))->put('/dashboard/system/settings', $this->form(['tickets_enabled' => '0']));
        auth()->logout();
        Settings::flush();

        $this->get('/aduan')->assertStatus(503)->assertSee('ditutup sementara')->assertHeader('Retry-After');
        $this->post('/aduan', ['reporter_name' => 'x'])->assertStatus(503);
        $this->assertDatabaseCount('tickets', 0);
        $this->get('/aduan/status')->assertOk();
    }

    public function test_manual_dry_run_changes_nothing_but_is_recorded(): void
    {
        config(['csirt.retention.audit_log_days' => 30]);
        $old = AuditLog::create(['event' => 'lama']);
        AuditLog::query()->whereKey($old->id)->update(['created_at' => now()->subDays(31)]);
        $super = $this->user('superadmin');

        $this->actingAs($super)->post('/dashboard/system/tasks/prune/run', ['dry_run' => '1'])->assertSessionHas('success');

        $this->assertModelExists($old);
        $run = TaskRun::firstOrFail();
        $this->assertTrue($run->dry_run);
        $this->assertSame('manual', $run->trigger);
        $this->assertSame($super->id, $run->user_id);
        $this->assertStringContainsString('dry-run', $run->output);
    }

    public function test_manual_run_applies_retention_and_is_audited(): void
    {
        config(['csirt.retention.audit_log_days' => 30]);
        $old = AuditLog::create(['event' => 'lama']);
        AuditLog::query()->whereKey($old->id)->update(['created_at' => now()->subDays(31)]);

        $this->actingAs($this->user('superadmin'))->post('/dashboard/system/tasks/prune/run', ['dry_run' => '0'])->assertSessionHas('success');

        $this->assertModelMissing($old);
        $this->assertFalse(TaskRun::firstOrFail()->dry_run);
        $this->assertDatabaseHas('audit_logs', ['event' => 'task_run_manual']);
    }

    public function test_only_registered_tasks_can_be_run(): void
    {
        $super = $this->user('superadmin');

        foreach (['migrate:fresh', 'prune;rm', '../x', 'tinker'] as $task) {
            $this->actingAs($super)->post('/dashboard/system/tasks/' . rawurlencode($task) . '/run')->assertNotFound();
        }

        $this->assertDatabaseCount('task_runs', 0);
    }

    public function test_scheduler_follows_dashboard_settings(): void
    {
        Artisan::call('schedule:list'); // memuat routes/console.php
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'csirt:prune');
        $this->assertNotNull($event, 'Tugas csirt:prune harus terdaftar di scheduler');

        $this->assertTrue($event->filtersPass($this->app));

        DB::table('settings')->insert(['key' => 'task_prune_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()]);
        Settings::flush();
        $this->assertFalse($event->filtersPass($this->app), 'Tugas yang dinonaktifkan di dashboard tidak boleh berjalan');
    }

    public function test_scheduler_heartbeat_is_reflected_in_status(): void
    {
        $super = $this->user('superadmin');

        $this->actingAs($super)->get('/dashboard/system')->assertSee('Belum pernah berjalan');

        TaskRunner::beat();
        $this->actingAs($super)->get('/dashboard/system')->assertSee('Aktif (detak')->assertDontSee('Belum pernah berjalan');

        $this->travel(10)->minutes();
        $this->actingAs($super)->get('/dashboard/system')->assertSee('Terakhir aktif');
    }

    public function test_status_page_warns_about_dangerous_configuration(): void
    {
        config(['csirt.login_whitelist' => ['*'], 'csirt.enforce_2fa' => false]);

        $this->actingAs($this->user('superadmin'))->get('/dashboard/system')
            ->assertSee('Terbuka untuk semua IP')
            ->assertSee('NONAKTIF');
    }

    public function test_settings_table_missing_does_not_break_boot(): void
    {
        DB::statement('DROP TABLE settings');
        Settings::flush();

        $this->assertSame([], Settings::stored());
        $this->assertTrue(Settings::bool('tickets_enabled'), 'Tanpa tabel, pakai default registry');
    }

    public function test_failed_task_is_recorded_as_failed_and_does_not_throw(): void
    {
        config(['csirt.retention.audit_log_days' => 30]);
        // Tugas retensi membaca tabel audit_logs; tanpa tabel itu perintah melempar exception.
        DB::statement('DROP TABLE audit_logs');

        $run = TaskRunner::run('prune', 'schedule');

        $this->assertSame('failed', $run->status);
        $this->assertSame('schedule', $run->trigger);
        $this->assertNull($run->user_id);
        $this->assertStringContainsString('Galat', $run->output);
        $this->assertSame(1, TaskRun::where('status', 'failed')->count());
    }

    public function test_successful_scheduled_run_is_recorded_without_user(): void
    {
        $run = TaskRunner::run('prune', 'schedule');

        $this->assertSame('success', $run->status);
        $this->assertNull($run->user_id);
        $this->assertNotNull($run->finished_at);
    }
}

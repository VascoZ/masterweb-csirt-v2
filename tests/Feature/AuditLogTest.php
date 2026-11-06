<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_changes_are_audited_with_actor_and_ip(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/dashboard/categories', ['name' => 'Berita', 'slug' => 'berita']);
        $this->actingAs($admin)->put('/dashboard/categories/berita', ['name' => 'Berita Baru', 'slug' => 'berita']);
        $this->actingAs($admin)->delete('/dashboard/categories/berita');

        $logs = AuditLog::where('auditable_type', Category::class)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'deleted'], $logs->pluck('event')->all());
        $this->assertSame($admin->id, $logs[0]->user_id);
        $this->assertSame('127.0.0.1', $logs[0]->ip_address);
        $this->assertSame(['name' => 'Berita'], $logs[1]->old_values);
        $this->assertSame(['name' => 'Berita Baru'], $logs[1]->new_values);
    }

    public function test_sensitive_values_are_never_stored(): void
    {
        $user = User::factory()->create();
        $user->update(['password' => 'Rahasia!234']);

        $log = AuditLog::where('auditable_type', User::class)->where('event', 'updated')->firstOrFail();
        $this->assertSame('[disembunyikan]', $log->new_values['password']);
        $this->assertStringNotContainsString('Rahasia', json_encode(AuditLog::all()->toArray()));
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::create(['event' => 'test']);

        $this->expectException(LogicException::class);
        $log->update(['event' => 'diubah']);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = AuditLog::create(['event' => 'test']);

        $this->expectException(LogicException::class);
        $log->delete();
    }
}

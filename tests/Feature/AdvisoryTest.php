<?php

namespace Tests\Feature;

use App\Models\Advisory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_advisory_with_sequential_code(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/dashboard/advisories', $this->payload(['slug' => 'pertama']))->assertRedirect();
        $this->actingAs($admin)->post('/dashboard/advisories', $this->payload(['slug' => 'kedua']))->assertRedirect();

        $year = now()->format('Y');
        $this->assertSame(["ADV-{$year}-0001", "ADV-{$year}-0002"], Advisory::orderBy('id')->pluck('code')->all());
        $this->assertNotNull(Advisory::first()->published_at);
    }

    public function test_only_published_tlp_clear_advisories_are_public(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/dashboard/advisories', $this->payload(['slug' => 'publik', 'title' => 'Advisory Publik']));
        $this->actingAs($admin)->post('/dashboard/advisories', $this->payload(['slug' => 'amber', 'title' => 'Advisory Amber', 'tlp' => 'AMBER']));
        $this->actingAs($admin)->post('/dashboard/advisories', $this->payload(['slug' => 'draf', 'title' => 'Advisory Draf', 'published' => '0']));
        auth()->logout();

        $this->get('/advisories')->assertOk()
            ->assertSee('Advisory Publik')
            ->assertDontSee('Advisory Amber')
            ->assertDontSee('Advisory Draf');

        $this->get('/advisories/publik')->assertOk()->assertSee('CVE-2026-1234');
        $this->get('/advisories/amber')->assertNotFound();
        $this->get('/advisories/draf')->assertNotFound();
    }

    public function test_advisory_content_is_sanitised_and_cve_validated(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/dashboard/advisories', $this->payload(['cve' => 'bukan-cve']))->assertSessionHasErrors('cve');

        $this->actingAs($admin)->post('/dashboard/advisories', $this->payload(['body' => '<div>Aman<script>alert(1)</script></div>']));
        $this->assertStringNotContainsString('<script', Advisory::firstOrFail()->body);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Kerentanan Kritis',
            'slug' => 'kerentanan-kritis',
            'tlp' => 'CLEAR',
            'severity' => 'critical',
            'cve' => 'CVE-2026-1234',
            'summary' => 'Ringkasan advisory.',
            'body' => '<div>Deskripsi</div>',
            'recommendation' => '<div>Perbarui</div>',
            'references' => "https://www.cve.org/\njavascript:alert(1)",
            'published' => '1',
        ];
    }
}

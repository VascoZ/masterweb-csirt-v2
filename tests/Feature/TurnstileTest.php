<?php

namespace Tests\Feature;

use App\Rules\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_accepts_valid_token(): void
    {
        config(['turnstile.disabled' => false, 'turnstile.secret_key' => 'secret']);

        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => true]),
        ]);

        $failed = false;
        (new Turnstile())->validate('cf-turnstile-response', 'token', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
        Http::assertSent(fn ($request) => $request['secret'] === 'secret' && $request['response'] === 'token');
    }

    public function test_rule_rejects_invalid_token(): void
    {
        config(['turnstile.disabled' => false, 'turnstile.secret_key' => 'secret']);

        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response(['success' => false]),
        ]);

        $message = null;
        (new Turnstile())->validate('cf-turnstile-response', 'token', function ($fail) use (&$message) {
            $message = $fail;
        });

        $this->assertNotNull($message);
    }

    public function test_rule_fails_closed_without_secret(): void
    {
        config(['turnstile.disabled' => false, 'turnstile.secret_key' => null]);

        $message = null;
        (new Turnstile())->validate('cf-turnstile-response', 'token', function ($fail) use (&$message) {
            $message = $fail;
        });

        $this->assertNotNull($message);
    }

    public function test_login_page_renders_turnstile_widget(): void
    {
        config(['turnstile.disabled' => false, 'turnstile.site_key' => 'site-key-test']);

        $this->get('/csirt-login')
            ->assertOk()
            ->assertSee('cf-turnstile', false)
            ->assertSee('site-key-test');
    }
}

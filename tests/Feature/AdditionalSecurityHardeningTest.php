<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdditionalSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_array_body_returns_validation_not_server_error(): void
    {
        $response = $this->post('/login', [
            'email' => ['x'],
            'password' => ['x'],
            'cf-turnstile-response' => ['x'],
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_public_author_filter_does_not_reveal_draft_only_users(): void
    {
        $category = Category::create(['name' => 'Umum', 'slug' => 'umum']);
        $draftWriter = $this->user('writer', ['name' => 'Penulis Draf Unik', 'username' => 'penulis-draf-unik']);

        $draft = new Post([
            'title' => 'Draf', 'slug' => 'draf-penulis-unik', 'category_id' => $category->id,
            'excerpt' => 'x', 'body' => '<div>x</div>', 'published' => false,
        ]);
        $draft->user_id = $draftWriter->id;
        $draft->save();

        $this->get('/posts?author=penulis-draf-unik')
            ->assertOk()
            ->assertDontSee('Penulis Draf Unik');

        $publicWriter = $this->user('writer', ['name' => 'Penulis Publik Unik', 'username' => 'penulis-publik-unik']);

        $published = new Post([
            'title' => 'Publik', 'slug' => 'publik-penulis-unik', 'category_id' => $category->id,
            'excerpt' => 'x', 'body' => '<div>x</div>', 'published' => true,
        ]);
        $published->user_id = $publicWriter->id;
        $published->save();

        $this->get('/posts?author=penulis-publik-unik')
            ->assertOk()
            ->assertSee('Penulis Publik Unik');
    }

    public function test_security_password_rejects_array_current_password_without_server_error(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/dashboard/security/password', [
            'current_password' => ['x'],
            'password' => 'Baru!234567x',
            'password_confirmation' => 'Baru!234567x',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_audit_log_ignores_invalid_date_filter(): void
    {
        $super = $this->user('superadmin');

        $this->actingAs($super)
            ->get('/dashboard/audit-logs?from=bukan-tanggal')
            ->assertOk();
    }
}

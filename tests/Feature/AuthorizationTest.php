<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function adminUrls(): array
    {
        return [
            ['/dashboard/tickets'], ['/dashboard/advisories'], ['/dashboard/categories'], ['/dashboard/footers'],
            ['/dashboard/properties'], ['/dashboard/profils'], ['/dashboard/services'], ['/dashboard/files'],
            ['/dashboard/keys'], ['/dashboard/guidances'], ['/dashboard/advisories/create'],
        ];
    }

    #[DataProvider('adminUrls')]
    public function test_writer_cannot_open_admin_pages(string $url): void
    {
        $this->actingAs($this->user())->get($url)->assertForbidden();
    }

    #[DataProvider('adminUrls')]
    public function test_admin_can_open_admin_pages(string $url): void
    {
        $this->actingAs($this->user('admin'))->get($url)->assertOk();
    }

    public function test_only_superadmin_manages_users_and_audit_logs(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/dashboard/users')->assertForbidden();
        $this->actingAs($admin)->get('/dashboard/audit-logs')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => 'access_denied', 'user_id' => $admin->id]);

        $super = $this->user('superadmin');
        $this->actingAs($super)->get('/dashboard/users')->assertOk();
        $this->actingAs($super)->get('/dashboard/audit-logs')->assertOk();
    }

    public function test_writer_cannot_update_someone_elses_post(): void
    {
        $post = $this->makePost($this->user());
        $other = $this->user();

        $this->actingAs($other)->get("/dashboard/posts/{$post->slug}")->assertForbidden();
        $this->actingAs($other)->put("/dashboard/posts/{$post->slug}", $this->postPayload())->assertForbidden();
    }

    public function test_writer_can_edit_own_draft_but_cannot_publish(): void
    {
        $writer = $this->user();
        $post = $this->makePost($writer);

        $this->actingAs($writer)->put("/dashboard/posts/{$post->slug}", $this->postPayload(['published' => '1']))
            ->assertRedirect(route('dashboard.posts.index'));

        $post->refresh();
        $this->assertSame('Judul Baru', $post->title);
        $this->assertFalse($post->published, 'Penulis tidak boleh menerbitkan artikel');
    }

    public function test_writer_cannot_edit_own_published_post(): void
    {
        $writer = $this->user();
        $post = $this->makePost($writer, true);

        $this->actingAs($writer)->put("/dashboard/posts/{$post->slug}", $this->postPayload())->assertForbidden();
    }

    public function test_admin_publishes_post_and_original_author_is_kept(): void
    {
        $writer = $this->user();
        $post = $this->makePost($writer);

        $this->actingAs($this->user('admin'))->put("/dashboard/posts/{$post->slug}", $this->postPayload(['published' => '1']));

        $post->refresh();
        $this->assertTrue($post->published);
        $this->assertSame($writer->id, (int) $post->user_id);
    }

    public function test_superadmin_cannot_change_own_role(): void
    {
        $super = $this->user('superadmin');

        $this->actingAs($super)->put("/dashboard/users/{$super->username}", [
            'name' => $super->name, 'username' => $super->username, 'email' => $super->email, 'is_admin' => '0',
        ])->assertRedirect();

        $this->assertTrue($super->fresh()->isSuperadmin());
        $this->assertTrue($super->fresh()->is_admin);
    }

    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'x', 'username' => 'x', 'email' => 'x@x.x', 'password' => 'x', 'is_admin' => true, 'is_superadmin' => true]);

        $this->assertNull($user->is_admin);
        $this->assertNull($user->is_superadmin);
    }

    private function makePost(User $author, bool $published = false): Post
    {
        $post = new Post(['title' => 'Judul', 'slug' => 'judul-' . uniqid(), 'category_id' => Category::firstOrCreate(['slug' => 'umum'], ['name' => 'Umum'])->id, 'excerpt' => 'x', 'body' => 'x', 'published' => $published]);
        $post->user_id = $author->id;
        $post->save();

        return $post;
    }

    private function postPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Judul Baru',
            'slug' => 'judul-baru-' . uniqid(),
            'category_id' => Category::first()->id,
            'body' => '<div>Isi baru</div>',
        ];
    }
}

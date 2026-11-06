<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\File;
use App\Models\Footer;
use App\Models\Guidance;
use App\Models\Key;
use App\Models\Post;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_footer_maps_must_be_google_embed_and_is_stored_as_url(): void
    {
        $admin = $this->user('admin');
        $base = ['name' => 'Tim', 'address' => 'Jl. Contoh 1', 'telephone' => '021-123456', 'email' => 'tim@contoh.id'];

        $this->actingAs($admin)->post('/dashboard/footers', $base + ['maps' => '<iframe src="https://evil.example/x"></iframe>'])
            ->assertSessionHasErrors('maps');

        $this->actingAs($admin)->post('/dashboard/footers', $base + ['maps' => '<iframe src="https://www.google.com/maps/embed?pb=1" onload="x()"></iframe>'])
            ->assertRedirect();

        $this->assertSame('https://www.google.com/maps/embed?pb=1', Footer::firstOrFail()->maps);
    }

    public function test_profile_content_is_sanitised_and_site_cache_refreshes(): void
    {
        $admin = $this->user('admin');

        $this->get('/profil')->assertSee('Profil belum diisi');

        $this->actingAs($admin)->post('/dashboard/profils', ['name' => 'Tim Baru', 'content' => '<div>Profil<script>x()</script></div>']);

        $this->assertStringNotContainsString('<script', Profil::firstOrFail()->content);
        $this->get('/profil')->assertSee('Profil Tim Baru');
    }

    public function test_uploads_use_random_names_and_svg_is_rejected(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/dashboard/files', ['file' => UploadedFile::fake()->create('../../rfc2350.pdf', 10, 'application/pdf')]);
        $file = File::firstOrFail();
        $this->assertSame('rfc2350.pdf', $file->name);
        $this->assertStringNotContainsString('rfc2350', $file->path);
        Storage::disk('public')->assertExists($file->path);

        $this->actingAs($admin)->post('/dashboard/properties', ['property' => 'Logo', 'name' => 'Logo SVG',
            'image' => UploadedFile::fake()->createWithContent('logo.svg', '<svg onload="alert(1)"></svg>')])
            ->assertSessionHasErrors('image');

        $this->actingAs($admin)->delete("/dashboard/files/{$file->id}");
        Storage::disk('public')->assertMissing($file->path);
    }

    public function test_pgp_key_upload_checks_content(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/dashboard/keys', ['file' => UploadedFile::fake()->createWithContent('kunci.asc', 'bukan kunci')])
            ->assertSessionHasErrors('file');

        $this->actingAs($admin)->post('/dashboard/keys', ['file' => UploadedFile::fake()->createWithContent('kunci.asc', "-----BEGIN PGP PUBLIC KEY BLOCK-----\nabc\n-----END PGP PUBLIC KEY BLOCK-----")])
            ->assertRedirect();
        $this->assertStringEndsWith('.asc', Key::firstOrFail()->path);
    }

    public function test_guidance_can_be_renamed_without_new_file(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/dashboard/guidances', ['name' => 'Panduan Ransomware', 'file' => UploadedFile::fake()->create('p.pdf', 20, 'application/pdf')]);
        $guidance = Guidance::firstOrFail();

        $this->actingAs($admin)->put("/dashboard/guidances/{$guidance->slug}", ['name' => 'Panduan Ransomware v2'])->assertRedirect();

        $this->assertSame('panduan-ransomware-v2', $guidance->fresh()->slug);
        $this->assertSame($guidance->path, $guidance->fresh()->path);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $admin = $this->user('admin');
        $category = Category::create(['name' => 'Berita', 'slug' => 'berita']);
        $post = new Post(['title' => 'A', 'slug' => 'a', 'category_id' => $category->id, 'excerpt' => 'a', 'body' => 'a']);
        $post->user_id = $admin->id;
        $post->save();

        $this->actingAs($admin)->delete('/dashboard/categories/berita')->assertSessionHas('error');
        $this->assertModelExists($category);
    }

    public function test_writer_post_with_image_is_saved_as_draft(): void
    {
        $writer = $this->user();
        $category = Category::create(['name' => 'Berita', 'slug' => 'berita']);

        $this->actingAs($writer)->post('/dashboard/posts', [
            'title' => 'Tulisan Saya', 'slug' => 'tulisan-saya', 'category_id' => $category->id,
            'body' => '<div>Isi <b>tebal</b></div>', 'published' => '1',
            'image' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertRedirect(route('dashboard.posts.index'));

        $post = Post::firstOrFail();
        $this->assertFalse($post->published);
        $this->assertSame($writer->id, (int) $post->user_id);
        $this->assertSame('Isi tebal', $post->excerpt);
        Storage::disk('public')->assertExists($post->image);
    }

    public function test_superadmin_creates_user_with_strong_password(): void
    {
        $super = $this->user('superadmin');

        $this->actingAs($super)->post('/dashboard/users', ['name' => 'Baru', 'username' => 'baru', 'email' => 'baru@contoh.id', 'password' => '12345'])
            ->assertSessionHasErrors('password');

        $this->actingAs($super)->post('/dashboard/users', ['name' => 'Baru', 'username' => 'baru', 'email' => 'baru@contoh.id', 'password' => 'Kuat!2345x', 'is_admin' => '1'])
            ->assertRedirect();

        $this->assertTrue(User::where('username', 'baru')->firstOrFail()->is_admin);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Footer;
use App\Models\Key;
use App\Models\Post;
use App\Models\Profil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function publicUrls(): array
    {
        return [['/'], ['/profil'], ['/file'], ['/service'], ['/guidance'], ['/contact'], ['/posts'], ['/advisories'], ['/aduan'], ['/aduan/status']];
    }

    #[DataProvider('publicUrls')]
    public function test_public_pages_render_on_empty_database(string $url): void
    {
        $this->get($url)->assertOk();
    }

    #[DataProvider('publicUrls')]
    public function test_public_pages_render_with_content(string $url): void
    {
        $this->seedSiteContent();

        $this->get($url)->assertOk()->assertSee('CSIRT Uji');
    }

    public function test_security_headers_and_csp_are_sent(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_draft_post_is_not_publicly_accessible(): void
    {
        $post = $this->post_('Draf Rahasia', false);

        $this->get('/posts/' . $post->slug)->assertNotFound();
    }

    public function test_search_does_not_leak_draft_posts(): void
    {
        $this->post_('Artikel Terbit', true, '<div>kata-kunci</div>');
        $this->post_('Draf Rahasia', false, '<div>kata-kunci</div>');

        $this->get('/posts?search=kata-kunci')
            ->assertOk()
            ->assertSee('Artikel Terbit')
            ->assertDontSee('Draf Rahasia');
    }

    public function test_stored_xss_in_post_body_is_neutralised(): void
    {
        $post = $this->post_('Artikel XSS', true, '<div>Halo<script>alert(1)</script><img src=x onerror="alert(2)"><a href="javascript:alert(3)">klik</a></div>');

        $html = $this->get('/posts/' . $post->slug)->assertOk()->getContent();

        $this->assertStringContainsString('Halo', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_legacy_maps_iframe_is_rebuilt_safely(): void
    {
        $this->seedSiteContent();
        Footer::query()->update(['maps' => '<iframe src="https://www.google.com/maps/embed?pb=abc" onload="alert(1)"></iframe>']);

        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertStringContainsString('src="https://www.google.com/maps/embed?pb=abc"', $html);
        $this->assertStringNotContainsString('onload', $html);
    }

    public function test_report_buttons_always_use_internal_form_even_with_legacy_profile_link(): void
    {
        // Data 2022 mewajibkan kolom link (mis. https://bssn.go.id) untuk tombol "Lapor Insiden".
        Profil::create(['name' => 'CSIRT Lama', 'slug' => 'csirt-lama', 'content' => '<div>x</div>', 'link' => 'https://bssn.go.id']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('tickets.create') . '"', $html);
        $this->assertStringNotContainsString('href="https://bssn.go.id"', $html);
    }

    public function test_security_txt_follows_rfc_9116(): void
    {
        $this->seedSiteContent();

        $response = $this->get('/.well-known/security.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString('Contact: mailto:tim@csirt.test', $body);
        $this->assertStringContainsString('Contact: ' . route('tickets.create'), $body);
        $this->assertMatchesRegularExpression('/^Expires: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/m', $body);
        $this->assertStringContainsString('Encryption: ', $body);
        $this->assertStringContainsString('Canonical: ' . url('/.well-known/security.txt'), $body);

        preg_match('/^Expires: (.+)$/m', $body, $m);
        $this->assertTrue(now()->lt($m[1]) && now()->addYear()->addDay()->gt($m[1]), 'Expires harus < 1 tahun ke depan');

        $this->get('/security.txt')->assertRedirect('/.well-known/security.txt');
    }

    private function seedSiteContent(): void
    {
        Profil::create(['name' => 'CSIRT Uji', 'slug' => 'csirt-uji', 'content' => '<div>Profil tim uji</div>']);
        Footer::create(['name' => 'CSIRT Uji', 'slug' => 'csirt-uji', 'address' => 'Jl. Uji No. 1', 'telephone' => '021123456', 'email' => 'tim@csirt.test', 'maps' => 'https://www.google.com/maps/embed?pb=xyz']);
        Key::create(['name' => 'public.asc', 'path' => 'public-key/test.asc']);
        $this->post_('Artikel Pertama', true);
    }

    private function post_(string $title, bool $published, string $body = '<div>Isi</div>'): Post
    {
        $category = Category::firstOrCreate(['slug' => 'umum'], ['name' => 'Umum']);
        $post = new Post(['title' => $title, 'slug' => \Illuminate\Support\Str::slug($title), 'category_id' => $category->id, 'excerpt' => 'x', 'body' => $body, 'published' => $published]);
        $post->user_id = $this->user()->id;
        $post->save();

        return $post;
    }
}

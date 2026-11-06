<?php

namespace Tests\Feature;

use App\Enums\Severity;
use App\Enums\Tlp;
use App\Models\Advisory;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\File;
use App\Models\Footer;
use App\Models\Guidance;
use App\Models\ImageProperty;
use App\Models\Key;
use App\Models\Post;
use App\Models\Profil;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Smoke test: setiap halaman GET dashboard harus ter-render tanpa error
 * untuk superadmin, dengan data contoh di setiap tabel.
 */
class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_dashboard_page_renders(): void
    {
        $super = $this->user('superadmin', ['username' => 'superadmin']);
        $bindings = $this->seedEverything($super);

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with((string) $r->getName(), 'dashboard.') && in_array('GET', $r->methods()))
            ->reject(fn (Route $r) => str_ends_with($r->getName(), 'checkSlug') || str_ends_with($r->getName(), 'attachments.download'));

        $this->assertGreaterThan(30, $routes->count());

        foreach ($routes as $route) {
            $params = collect($route->parameterNames())->mapWithKeys(fn ($p) => [$p => $bindings[$p]])->all();
            $url = route($route->getName(), $params);

            $response = $this->actingAs($super)->get($url);
            $this->assertSame(200, $response->status(), "{$route->getName()} ({$url}) mengembalikan {$response->status()}");
        }
    }

    public function test_writer_dashboard_renders(): void
    {
        $writer = $this->user();

        $this->actingAs($writer)->get('/dashboard')->assertOk()->assertSee('Artikel Anda')->assertDontSee('Aduan Insiden');
        $this->actingAs($writer)->get('/dashboard/posts/create')->assertOk()->assertDontSee('Terbitkan artikel');
    }

    private function seedEverything($user): array
    {
        $category = Category::create(['name' => 'Umum', 'slug' => 'umum']);
        $post = new Post(['title' => 'Post', 'slug' => 'post', 'category_id' => $category->id, 'excerpt' => 'x', 'body' => '<div>x</div>', 'published' => true]);
        $post->user_id = $user->id;
        $post->save();

        $advisory = new Advisory(['title' => 'Adv', 'slug' => 'adv', 'tlp' => Tlp::Amber, 'severity' => Severity::High, 'cve' => 'CVE-2026-1111', 'summary' => 's', 'body' => '<div>b</div>', 'references' => 'https://contoh.id', 'published' => true]);
        $advisory->user_id = $user->id;
        $advisory->save();

        $ticket = Ticket::create(['reporter_name' => 'A', 'reporter_email' => 'a@a.id', 'category' => 'phishing', 'description' => str_repeat('x', 30), 'tlp' => 'AMBER']);
        $ticket->attachments()->create(['original_name' => 'a.txt', 'path' => 'tickets/1/a.txt', 'size' => 10, 'sha256' => str_repeat('a', 64)]);
        $ticket->updates()->create(['status' => 'new', 'note' => 'n', 'is_public' => true]);

        AuditLog::create(['event' => 'test', 'old_values' => ['a' => 1], 'new_values' => ['a' => 2]]);

        return [
            'post' => $post->slug,
            'category' => $category->slug,
            'footer' => Footer::create(['name' => 'F', 'slug' => 'f', 'address' => 'a', 'telephone' => '021123', 'email' => 'f@f.id', 'maps' => 'https://www.google.com/maps/embed?pb=1'])->slug,
            'property' => ImageProperty::create(['property' => 'Logo', 'name' => 'Logo', 'slug' => 'logo', 'image' => 'image-property/x.png'])->slug,
            'profil' => Profil::create(['name' => 'P', 'slug' => 'p', 'content' => '<div>p</div>'])->slug,
            'service' => Service::create(['name' => 'S', 'slug' => 's', 'content' => '<div>s</div>'])->slug,
            'file' => File::create(['name' => 'r.pdf', 'path' => 'uploads/r.pdf'])->id,
            'key' => Key::create(['name' => 'k.asc', 'path' => 'public-key/k.asc'])->id,
            'guidance' => Guidance::create(['name' => 'G', 'slug' => 'g', 'file_name' => 'g.pdf', 'size' => 1000, 'path' => 'uploads-guidances/g.pdf'])->slug,
            'advisory' => $advisory->slug,
            'ticket' => $ticket->ticket_number,
            'user' => $user->username,
        ];
    }
}

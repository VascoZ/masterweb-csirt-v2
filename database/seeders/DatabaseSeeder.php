<?php

namespace Database\Seeders;

use App\Enums\Severity;
use App\Enums\Tlp;
use App\Models\Advisory;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk pengembangan lokal SAJA. Untuk instalasi produksi
 * gunakan `php artisan csirt:install`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Seeder data contoh tidak boleh dijalankan di produksi. Gunakan php artisan csirt:install.');

            return;
        }

        $admin = User::factory()->superadmin()->create(['name' => 'Admin Demo', 'username' => 'admin', 'email' => 'admin@csirt.test']);
        User::factory()->create(['name' => 'Penulis Demo', 'username' => 'penulis', 'email' => 'penulis@csirt.test']);

        $category = Category::create(['name' => 'Edukasi Keamanan Siber', 'slug' => 'edukasi-keamanan-siber']);

        foreach (range(1, 3) as $i) {
            $post = new Post([
                'title' => "Contoh Artikel {$i}",
                'slug' => "contoh-artikel-{$i}",
                'category_id' => $category->id,
                'excerpt' => 'Ringkasan contoh artikel.',
                'body' => '<div>Isi contoh artikel tentang kebersihan siber.</div>',
                'published' => true,
                'published_at' => now(),
            ]);
            $post->user_id = $admin->id;
            $post->save();
        }

        $advisory = new Advisory([
            'title' => 'Contoh Kerentanan Kritis pada Aplikasi Web',
            'slug' => 'contoh-kerentanan-kritis',
            'tlp' => Tlp::Clear,
            'severity' => Severity::Critical,
            'cve' => 'CVE-2026-0001',
            'summary' => 'Contoh advisory untuk pengujian tampilan.',
            'body' => '<div>Deskripsi contoh.</div>',
            'recommendation' => '<div>Segera perbarui ke versi terbaru.</div>',
            'references' => "https://www.cve.org/\nhttps://nvd.nist.gov/",
            'published' => true,
        ]);
        $advisory->user_id = $admin->id;
        $advisory->save();

        $this->command->info('Akun demo: admin@csirt.test / Password!123 (2FA wajib saat login pertama).');
    }
}

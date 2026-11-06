<?php

namespace App\Support;

use App\Models\Category;
use App\Models\File;
use App\Models\Footer;
use App\Models\ImageProperty;
use App\Models\Key;
use App\Models\Post;
use App\Models\Profil;
use Illuminate\Support\Facades\Cache;

/**
 * Data identitas situs yang dipakai hampir di setiap halaman.
 * Sebelumnya di-query ulang (8-9 query) pada setiap route; sekarang
 * di-cache dan dibersihkan otomatis lewat trait FlushesSiteCache.
 */
class SiteData
{
    private const CACHE_KEY = 'csirt.site-data';

    private static ?array $memo = null;

    public static function all(): array
    {
        return self::$memo ??= Cache::rememberForever(self::CACHE_KEY, fn () => [
            'profile' => Profil::latest()->first(),
            'footer' => Footer::latest()->first(),
            'logo' => ImageProperty::where('property', 'Logo')->latest()->first(),
            'banner' => ImageProperty::where('property', 'Banner')->latest()->first(),
            'pgpKey' => Key::latest()->first(),
            'rfc2350' => File::latest()->first(),
            'categories' => Category::orderBy('name')->get(),
            'latestPosts' => Post::published()->latest()->take(4)->get(),
        ]);
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function name(): string
    {
        return self::get('profile')?->name ?? config('app.name');
    }

    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }
}

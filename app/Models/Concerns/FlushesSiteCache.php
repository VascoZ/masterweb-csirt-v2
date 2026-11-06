<?php

namespace App\Models\Concerns;

use App\Support\SiteData;

/**
 * Menghapus cache data situs (logo, footer, profil, dll.) setiap kali isinya berubah.
 */
trait FlushesSiteCache
{
    public static function bootFlushesSiteCache(): void
    {
        static::saved(fn () => SiteData::flush());
        static::deleted(fn () => SiteData::flush());
    }
}

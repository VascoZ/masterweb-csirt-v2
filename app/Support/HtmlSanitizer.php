<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitasi HTML untuk konten editor Trix (post, profil, layanan, advisory)
 * dan pembangunan ulang embed Google Maps pada footer.
 */
class HtmlSanitizer
{
    private static ?SymfonyHtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return self::sanitizer()->sanitize($html);
    }

    private static function sanitizer(): SymfonyHtmlSanitizer
    {
        if (self::$sanitizer) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->dropAttribute('style', '*')
            ->dropAttribute('class', '*')
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->withMaxInputLength(500_000);

        return self::$sanitizer = new SymfonyHtmlSanitizer($config);
    }

    /**
     * Membangun ulang iframe Google Maps dari input (kode embed atau URL)
     * sehingga hanya atribut aman yang tersisa. Mengembalikan null bila
     * tidak ditemukan URL embed Google Maps yang valid.
     */
    public static function mapsEmbed(?string $input): ?string
    {
        $url = self::mapsUrl($input);

        return $url === null ? null
            : '<iframe src="' . e($url) . '" width="600" height="450" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Peta lokasi"></iframe>';
    }

    public static function mapsUrl(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $input, $match)) {
            $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        } else {
            $url = trim(html_entity_decode($input, ENT_QUOTES | ENT_HTML5));
        }

        return preg_match('#^https://(www\.)?google\.[a-z.]+/maps/embed\?[^\s"\'<>]*$#i', $url) ? $url : null;
    }
}

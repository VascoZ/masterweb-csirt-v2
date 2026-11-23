<?php

namespace App\Support;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Value;

/**
 * Content Security Policy situs CSIRT. Semua aset dibundel lokal oleh Vite,
 * kecuali Google Maps (iframe) dan Cloudflare Turnstile (skrip, iframe, XHR).
 */
class CspPreset implements Preset
{
    private const TURNSTILE = 'https://challenges.cloudflare.com';

    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::CONNECT, [Keyword::SELF, self::TURNSTILE])
            ->add(Directive::FONT, [Keyword::SELF, 'data:'])
            ->add(Directive::FORM_ACTION, Keyword::SELF)
            ->add(Directive::FRAME, [Keyword::SELF, 'https://www.google.com', self::TURNSTILE])
            ->add(Directive::FRAME_ANCESTORS, Keyword::SELF)
            ->add(Directive::IMG, [Keyword::SELF, 'data:', 'blob:'])
            ->add(Directive::MEDIA, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::SCRIPT, [Keyword::SELF, self::TURNSTILE])
            ->add(Directive::STYLE, Keyword::SELF)
            ->addNonce(Directive::SCRIPT)
            ->addNonce(Directive::STYLE);

        if (request()->isSecure()) {
            $policy->add(Directive::UPGRADE_INSECURE_REQUESTS, Value::NO_VALUE);
        }
    }
}

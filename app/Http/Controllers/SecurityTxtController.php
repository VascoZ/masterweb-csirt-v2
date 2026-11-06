<?php

namespace App\Http\Controllers;

use App\Support\SiteData;
use Illuminate\Http\Response;

/**
 * /.well-known/security.txt sesuai RFC 9116, dibangkitkan dari data situs
 * sehingga field Expires selalu valid (maks. 1 tahun ke depan).
 */
class SecurityTxtController extends Controller
{
    public function __invoke(): Response
    {
        $contact = config('csirt.security_txt.contact') ?: SiteData::get('footer')?->email;
        $key = SiteData::get('pgpKey');

        $lines = array_filter([
            $contact ? 'Contact: ' . (str_contains($contact, ':') ? $contact : 'mailto:' . $contact) : null,
            'Contact: ' . route('tickets.create'),
            'Expires: ' . now()->utc()->addYear()->startOfDay()->format('Y-m-d\TH:i:s\Z'),
            $key ? 'Encryption: ' . asset('storage/' . $key->path) : null,
            'Preferred-Languages: ' . config('csirt.security_txt.languages'),
            'Canonical: ' . url('/.well-known/security.txt'),
            config('csirt.security_txt.policy_url') ? 'Policy: ' . config('csirt.security_txt.policy_url') : null,
        ]);

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

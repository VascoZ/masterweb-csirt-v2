<?php

return [

    'site_key' => env('TURNSTILE_SITE_KEY'),

    'secret_key' => env('TURNSTILE_SECRET_KEY'),

    'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),

    'timeout' => (int) env('TURNSTILE_TIMEOUT', 5),

    'disabled' => (bool) env('TURNSTILE_DISABLE', false),

];

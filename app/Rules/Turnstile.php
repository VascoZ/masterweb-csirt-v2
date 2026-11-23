<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Turnstile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (config('turnstile.disabled')) {
            return;
        }

        $secret = (string) config('turnstile.secret_key');

        if ($secret === '') {
            $fail('Verifikasi keamanan belum dikonfigurasi.');
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Selesaikan verifikasi keamanan terlebih dahulu.');
            return;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('turnstile.timeout', 5))
                ->post((string) config('turnstile.verify_url'), [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Verifikasi Turnstile gagal dihubungi.', ['error' => $e->getMessage()]);
            $fail('Verifikasi keamanan tidak dapat diproses. Silakan coba lagi.');
            return;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $fail('Verifikasi keamanan gagal. Silakan coba lagi.');
        }
    }
}

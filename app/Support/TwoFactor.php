<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) untuk login dashboard, kompatibel dengan Google Authenticator,
 * Microsoft Authenticator, FreeOTP, Aegis, dsb.
 */
class TwoFactor
{
    public function __construct(private Google2FA $engine = new Google2FA())
    {
    }

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->engine->getQRCodeUrl(SiteData::name(), $user->email, $secret);

        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd())))->writeString($url);

        // Buang deklarasi XML agar bisa disisipkan langsung ke HTML.
        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /**
     * Verifikasi kode dan cegah pemakaian ulang kode yang sama (replay).
     */
    public function verify(User $user, string $code, ?string $secret = null): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        $secret ??= $user->two_factor_secret;

        if (! $secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        // Dengan oldTimestamp (min. 0) library mengembalikan slot waktu kode yang cocok.
        $timestamp = $this->engine->verifyKeyNewer($secret, $code, (int) $user->two_factor_last_timestamp, 1);

        if ($timestamp === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_timestamp' => $timestamp])->saveQuietly();

        return true;
    }

    public function generateRecoveryCodes(): array
    {
        return Collection::times(8, fn () => Str::upper(Str::random(5) . '-' . Str::random(5)))->all();
    }

    /**
     * Gunakan satu kode pemulihan (sekali pakai).
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $code)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();

                return true;
            }
        }

        return false;
    }
}

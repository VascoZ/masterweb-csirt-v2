<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    /**
     * Mencatat satu kejadian ke audit log (database) dan ke kanal log keamanan
     * (file/stderr/syslog untuk SIEM). Kegagalan pencatatan tidak boleh
     * menggagalkan aksi utama, tetapi tetap ditulis ke log aplikasi.
     */
    public static function log(string $event, ?Model $subject = null, ?array $old = null, ?array $new = null, ?string $description = null, ?int $userId = null): void
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $entry = [
            'user_id' => $userId ?? auth()->id(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => $description ?? ($subject && method_exists($subject, 'auditLabel') ? class_basename($subject) . ': ' . $subject->auditLabel() : null),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : 'console',
        ];

        try {
            AuditLog::create($entry + ['old_values' => $old, 'new_values' => $new]);
        } catch (Throwable $e) {
            Log::error('Gagal mencatat audit log', ['event' => $event, 'error' => $e->getMessage()]);
        }

        // Salinan di luar database aplikasi (NIST SP 800-53 AU-9). Hanya nama field
        // yang berubah yang dikirim, bukan nilainya, agar tidak menyebarkan data pribadi.
        self::security($event, $entry + ['fields' => array_keys($new ?? $old ?? [])]);
    }

    /**
     * Kejadian keamanan yang cukup dicatat di kanal log keamanan (tidak di database),
     * mis. akses halaman login dari IP di luar whitelist.
     */
    public static function security(string $event, array $context = []): void
    {
        try {
            Log::channel(config('csirt.security_log_channel'))->info($event, $context + [
                'app' => config('app.name'),
                'ip_address' => $context['ip_address'] ?? request()?->ip(),
            ]);
        } catch (Throwable $e) {
            Log::error('Gagal menulis log keamanan', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}

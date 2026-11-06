<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    /**
     * Mencatat satu kejadian. Kegagalan pencatatan tidak boleh
     * menggagalkan aksi utama, tetapi tetap ditulis ke log aplikasi.
     */
    public static function log(string $event, ?Model $subject = null, ?array $old = null, ?array $new = null, ?string $description = null, ?int $userId = null): void
    {
        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            AuditLog::create([
                'user_id' => $userId ?? auth()->id(),
                'event' => $event,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'description' => $description ?? ($subject && method_exists($subject, 'auditLabel') ? class_basename($subject) . ': ' . $subject->auditLabel() : null),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : 'console',
            ]);
        } catch (Throwable $e) {
            Log::error('Gagal mencatat audit log', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}

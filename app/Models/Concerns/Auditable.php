<?php

namespace App\Models\Concerns;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat create/update/delete model ke tabel audit_logs.
 * Atribut sensitif tidak pernah disimpan nilainya.
 */
trait Auditable
{
    protected static array $auditRedacted = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_timestamp',
    ];

    protected static array $auditIgnored = ['updated_at', 'created_at', 'last_login_at', 'last_login_ip'];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLogger::log('created', $model, null, static::auditValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = array_diff_key($model->getChanges(), array_flip(static::$auditIgnored));
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            AuditLogger::log('updated', $model, static::auditValues($old), static::auditValues($changes));
        });

        static::deleted(function (Model $model) {
            AuditLogger::log('deleted', $model, static::auditValues($model->getAttributes()), null);
        });
    }

    protected static function auditValues(array $values): array
    {
        $values = array_diff_key($values, array_flip(static::$auditIgnored));

        foreach ($values as $key => $value) {
            if (in_array($key, static::$auditRedacted, true)) {
                $values[$key] = '[disembunyikan]';
            } elseif (is_string($value) && mb_strlen($value) > 500) {
                $values[$key] = mb_substr($value, 0, 500) . '…';
            }
        }

        return $values;
    }

    public function auditLabel(): string
    {
        return (string) ($this->title ?? $this->name ?? $this->ticket_number ?? $this->code ?? $this->getKey());
    }
}

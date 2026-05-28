<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create / update / delete events for a model into the append-only
 * audit_logs table. The user is resolved from whichever admin guard is active
 * (admin / super_admin) and falls back to "system" for jobs / console / queue
 * workers without an authenticated context.
 *
 * For updates, only changed attributes are stored — full snapshots for
 * everything would balloon storage on hot tables.
 *
 * Opt out per-attribute by setting `$auditExcept` on the model. The framework
 * timestamps (`updated_at`, `created_at`) are skipped automatically since they
 * change on every save.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            self::writeAuditLog($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            // Strip framework-managed columns; nothing meaningful to audit.
            unset($changes['updated_at']);

            $exclude = property_exists($model, 'auditExcept') ? $model->auditExcept : [];
            foreach ($exclude as $key) {
                unset($changes[$key]);
            }

            if ($changes === []) {
                return;
            }

            $original = [];
            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }

            self::writeAuditLog($model, 'updated', $original, $changes);
        });

        static::deleted(function (Model $model) {
            $event = (method_exists($model, 'isForceDeleting') && $model->isForceDeleting())
                ? 'force_deleted'
                : 'deleted';

            self::writeAuditLog($model, $event, $model->getOriginal(), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                self::writeAuditLog($model, 'restored', null, $model->getAttributes());
            });
        }
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private static function writeAuditLog(Model $model, string $event, ?array $oldValues, ?array $newValues): void
    {
        [$userId, $userType] = self::resolveAuditUser();

        AuditLog::withoutGlobalScopes()->create([
            'company_id' => $model->company_id ?? null,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'user_id' => $userId,
            'user_type' => $userType,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
        ]);
    }

    /**
     * @return array{0:?int, 1:string}
     */
    private static function resolveAuditUser(): array
    {
        foreach (['admin', 'super_admin'] as $guard) {
            $user = auth($guard)->user();
            if ($user !== null) {
                return [$user->getKey(), $guard];
            }
        }

        return [null, 'system'];
    }
}

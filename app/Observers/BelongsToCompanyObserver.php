<?php

namespace App\Observers;

use App\Services\TenantService;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

/**
 * Guards against tenant leaks on model retrieval:
 *
 *  1. Cross-company leak: model retrieved with a *different* company in context
 *     → CRITICAL log in all environments; RuntimeException thrown in local.
 *
 *  2. Unscoped retrieval: authenticated admin request but company context is null
 *     → WARNING log in all environments (suggests withoutGlobalScopes() bypass).
 */
class BelongsToCompanyObserver
{
    public function retrieved(Model $model): void
    {
        $contextId = TenantService::companyId();
        $modelCompanyId = $model->getAttribute('company_id');

        if ($contextId === null) {
            $this->maybeLogUnscopedRetrieval($model, $modelCompanyId);

            return;
        }

        if ($modelCompanyId === null || (int) $modelCompanyId === (int) $contextId) {
            return;
        }

        $message = sprintf(
            '[TENANT LEAK] %s#%s has company_id=%s but context is company_id=%s',
            $model::class,
            $model->getKey(),
            $modelCompanyId,
            $contextId,
        );

        Log::critical($message);

        if (app()->environment('local')) {
            throw new \RuntimeException($message);
        }
    }

    /**
     * Log when a tenanted model is retrieved during an authenticated admin
     * request but no company context is set — a signal that withoutGlobalScopes()
     * bypassed the tenant scope without an explicit ownership check.
     */
    private function maybeLogUnscopedRetrieval(Model $model, mixed $modelCompanyId): void
    {
        if (app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }

        $user = auth('admin')->user();

        if ($user === null) {
            return;
        }

        Log::warning('[TENANT AUDIT] unscoped-retrieval', [
            'model' => $model::class,
            'id' => $model->getKey(),
            'model_company_id' => $modelCompanyId,
            'user_id' => $user->getKey(),
            'path' => request()->path(),
            'ip' => request()->ip(),
        ]);
    }
}

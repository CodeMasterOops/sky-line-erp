<?php

namespace App\Observers;

use App\Services\TenantService;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

/**
 * Development/staging guard: logs a critical warning when a model is retrieved
 * whose company_id does not match the active tenant context. This catches
 * accidental global-scope bypasses during development without affecting production.
 */
class BelongsToCompanyObserver
{
    public function retrieved(Model $model): void
    {
        $contextId = TenantService::companyId();

        if ($contextId === null) {
            return;
        }

        $modelCompanyId = $model->getAttribute('company_id');

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
}

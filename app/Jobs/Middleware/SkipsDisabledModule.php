<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use App\Services\Modules\CompanyModuleService;

/**
 * Stops a queued job that belongs to a module the company has since switched
 * off. A job can sit on the queue across a module change, so the check has to
 * happen at execution time, not only at dispatch.
 *
 * The job is **completed, not failed**: a disabled module is a legitimate state,
 * not an error, and failing would page somebody over a deliberate
 * configuration. The skip is logged so the behaviour stays observable.
 *
 * Without a resolvable company the job runs — same fail-open stance as
 * `EnsureModuleEnabled`, which cannot cap what it cannot attribute.
 *
 * <code-snippet>
 * public function middleware(): array
 * {
 *     return [new ResetsTenantContext, new SkipsDisabledModule('inventory', $this->stock->company_id)];
 * }
 * </code-snippet>
 */
class SkipsDisabledModule
{
    public function __construct(
        private readonly string $moduleKey,
        private readonly ?int $companyId,
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        if ($this->companyId === null) {
            return $next($job);
        }

        if (app(CompanyModuleService::class)->isEnabled($this->moduleKey, $this->companyId)) {
            return $next($job);
        }

        Log::info('Skipped a job for a disabled module.', [
            'job' => $job::class,
            'module' => $this->moduleKey,
            'company_id' => $this->companyId,
        ]);

        return null;
    }
}

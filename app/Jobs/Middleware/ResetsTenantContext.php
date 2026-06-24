<?php

namespace App\Jobs\Middleware;

use Closure;
use App\Services\TenantService;

/**
 * Job middleware that clears the request-scoped tenant context after a job runs,
 * so the company/branch a job established for itself never leaks into the next
 * job on a long-lived queue worker (or Octane). The job still sets its own
 * context inside handle(); this only guarantees cleanup.
 */
class ResetsTenantContext
{
    public function handle(object $job, Closure $next): mixed
    {
        try {
            return $next($job);
        } finally {
            TenantService::reset();
        }
    }
}

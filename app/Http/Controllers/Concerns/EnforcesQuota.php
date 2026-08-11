<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use App\Services\Billing\QuotaService;

/**
 * One wording and one status code for every plan limit in the product.
 *
 * The response carries a machine-readable `code` so the SPA can offer an
 * upgrade instead of a generic validation error, and the usage numbers so it
 * can say "5 of 5 used" without a second call.
 *
 * Only ever refuses a *creation*. Nothing a company already has is affected by
 * being over a limit — see QuotaService.
 */
trait EnforcesQuota
{
    protected function refuseWhenOverQuota(string $quotaKey): ?JsonResponse
    {
        $company = auth('admin')->user()?->company;

        if (! $company) {
            return null;
        }

        $quotas = app(QuotaService::class);
        $state = $quotas->check($quotaKey, $company);

        if (! $state['exceeded']) {
            return null;
        }

        return response()->json([
            'message' => $quotas->message($state, $company),
            'code' => 'quota_exceeded',
            'limit_key' => $state['key'],
            'limit' => $state['limit'],
            'used' => $state['used'],
        ], 422);
    }
}

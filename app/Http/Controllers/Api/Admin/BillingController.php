<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Plan;
use App\Models\Company;
use App\Services\TenantService;
use App\Http\Controllers\Controller;
use App\Services\Billing\QuotaService;
use App\Http\Resources\Admin\PlanResource;
use App\Http\Resources\Admin\BillingSubscriptionResource;

class BillingController extends Controller
{
    public function subscription()
    {
        $company = $this->resolveCompany();

        if (! $company) {
            return response()->json([
                'message' => 'Company context is not available.',
            ], 400);
        }

        $subscription = $company->currentSubscription()
            ->with('plan')
            ->first();

        if (! $subscription) {
            return response()->json([
                'data' => null,
                'message' => 'No active subscription found.',
            ]);
        }

        return response()->json([
            'data' => BillingSubscriptionResource::make($subscription),
        ]);
    }

    /**
     * Headroom against the plan's quotas.
     *
     * Exists so a company sees "4 of 5 users" before it hits the wall rather
     * than only meeting the limit as a 422 at the worst moment. Quotas of
     * modules the company does not run are left out entirely — "0 of 0 gym
     * members" is noise for a hardware shop.
     */
    public function usage()
    {
        $company = $this->resolveCompany();

        if (! $company) {
            return response()->json([
                'message' => 'Company context is not available.',
            ], 400);
        }

        return response()->json([
            'data' => app(QuotaService::class)->all($company),
        ]);
    }

    public function plans()
    {
        $plans = Plan::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return PlanResource::collection($plans);
    }

    private function resolveCompany(): ?Company
    {
        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        if (! $companyId) {
            return null;
        }

        return Company::query()->find($companyId);
    }
}

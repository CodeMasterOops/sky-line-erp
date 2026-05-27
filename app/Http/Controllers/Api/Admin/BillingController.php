<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Plan;
use App\Models\Company;
use App\Services\TenantService;
use App\Http\Controllers\Controller;
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

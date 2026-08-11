<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Plan;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Jobs\ReconcileCompanyModulesJob;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\SuperAdmin\PlanResource;
use App\Http\Requests\Api\SuperAdmin\PlanRequest;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = Plan::query()
            ->withCount([
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn ($query) => $query->active(),
            ])
            ->filter($request->query())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->query('limit', 25));

        return PlanResource::collection($plans);
    }

    public function store(PlanRequest $request)
    {
        $data = $request->validated();

        if (! empty($data['is_default'])) {
            Plan::query()->update(['is_default' => false]);
        }

        $plan = Plan::create($data);

        return response()->json([
            'data' => PlanResource::make($plan),
            'message' => 'Plan created successfully.',
        ], 201);
    }

    public function show(Plan $plan)
    {
        $plan->loadCount([
            'subscriptions',
            'subscriptions as active_subscriptions_count' => fn ($query) => $query->active(),
        ]);

        return PlanResource::make($plan);
    }

    public function update(PlanRequest $request, Plan $plan)
    {
        if ($plan->is_default && array_key_exists('is_active', $request->validated()) && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => ['The default plan cannot be deactivated.'],
            ]);
        }

        $data = $request->validated();

        if (! empty($data['is_default'])) {
            Plan::query()->where('id', '!=', $plan->id)->update(['is_default' => false]);
        }

        $entitlementsBefore = $plan->modules;

        $plan->update($data);

        $reconciled = 0;

        if (array_key_exists('modules', $data) && $entitlementsBefore !== $plan->fresh()->modules) {
            $reconciled = $this->reconcileSubscribers($plan);
        }

        return response()->json([
            'data' => PlanResource::make($plan->fresh()),
            'reconciled_companies' => $reconciled,
            'message' => 'Plan updated successfully.',
        ]);
    }

    /**
     * Re-apply the plan cap to everyone already on this plan.
     *
     * Reconciliation used to run only on a *subscription* change, so removing a
     * module from a plan changed nothing for existing subscribers until their
     * next billing event — they kept a module the plan no longer sold. The job
     * is the same one `SubscriptionService` dispatches, so a downgrade only
     * hides modules (never deletes data) and a Super Admin override survives.
     */
    private function reconcileSubscribers(Plan $plan): int
    {
        $count = 0;

        Company::query()
            ->whereHas('subscriptions', fn ($query) => $query->active()->where('plan_id', $plan->id))
            ->select('id')
            ->chunkById(200, function ($companies) use (&$count): void {
                foreach ($companies as $company) {
                    ReconcileCompanyModulesJob::dispatch($company);
                    $count++;
                }
            });

        return $count;
    }

    public function destroy(Plan $plan)
    {
        if ($plan->is_default) {
            return response()->json([
                'message' => 'The default plan cannot be deleted.',
            ], 422);
        }

        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => 'This plan cannot be deleted because it has subscriptions.',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan deleted successfully.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Plan;
use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Enums\BillingCycleEnum;
use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use App\Http\Resources\SuperAdmin\SubscriptionResource;
use App\Http\Requests\Api\SuperAdmin\AssignSubscriptionRequest;
use App\Http\Requests\Api\SuperAdmin\UpdateSubscriptionRequest;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function index(Request $request)
    {
        $subscriptions = Subscription::query()
            ->with(['company.admin', 'plan', 'assignedBy'])
            ->filter($request->query())
            ->latest()
            ->paginate($request->query('limit', 25));

        return SubscriptionResource::collection($subscriptions);
    }

    public function store(AssignSubscriptionRequest $request)
    {
        $validated = $request->validated();

        $subscription = $this->subscriptionService->assignPlan(
            company: Company::findOrFail($validated['company_id']),
            plan: Plan::findOrFail($validated['plan_id']),
            billingCycle: BillingCycleEnum::from($validated['billing_cycle']),
            assignedBy: $request->user('super_admin'),
            notes: $validated['notes'] ?? null,
            trialEndsAt: isset($validated['trial_ends_at']) ? new \DateTimeImmutable($validated['trial_ends_at']) : null,
            endsAt: isset($validated['ends_at']) ? new \DateTimeImmutable($validated['ends_at']) : null,
        );

        $subscription->load(['company.admin', 'plan', 'assignedBy']);

        return response()->json([
            'data' => SubscriptionResource::make($subscription),
            'message' => 'Subscription assigned successfully.',
        ], 201);
    }

    public function show(Subscription $subscription)
    {
        $subscription->load(['company.admin', 'plan', 'assignedBy']);

        return SubscriptionResource::make($subscription);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription)
    {
        $validated = $request->validated();

        if (isset($validated['billing_cycle'])) {
            $billingCycle = BillingCycleEnum::from($validated['billing_cycle']);
            $validated['price'] = $subscription->plan->priceForCycle($billingCycle);
            $validated['billing_cycle'] = $billingCycle;
        }

        $subscription->update($validated);
        $subscription->load(['company.admin', 'plan', 'assignedBy']);

        return response()->json([
            'data' => SubscriptionResource::make($subscription),
            'message' => 'Subscription updated successfully.',
        ]);
    }

    public function cancel(Request $request, Subscription $subscription)
    {
        $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $subscription = $this->subscriptionService->cancel(
            $subscription,
            $request->user('super_admin'),
            $request->input('notes'),
        );

        $subscription->load(['company.admin', 'plan', 'assignedBy']);

        return response()->json([
            'data' => SubscriptionResource::make($subscription),
            'message' => 'Subscription cancelled successfully.',
        ]);
    }

    public function renew(Request $request, Subscription $subscription)
    {
        $validated = $request->validate([
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $subscription = $this->subscriptionService->renew(
            $subscription,
            isset($validated['ends_at']) ? new \DateTimeImmutable($validated['ends_at']) : null,
        );

        $subscription->load(['company.admin', 'plan', 'assignedBy']);

        return response()->json([
            'data' => SubscriptionResource::make($subscription),
            'message' => 'Subscription renewed successfully.',
        ]);
    }
}

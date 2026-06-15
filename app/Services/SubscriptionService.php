<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Company;
use App\Models\SuperAdmin;
use App\Models\Subscription;
use App\Enums\BillingCycleEnum;
use Illuminate\Support\Facades\DB;
use App\Enums\SubscriptionStatusEnum;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function assignDefaultPlan(Company $company): Subscription
    {
        $plan = Plan::query()->default()->active()->first();

        if (! $plan) {
            throw ValidationException::withMessages([
                'plan' => ['No default plan is configured. Please create a default plan first.'],
            ]);
        }

        return $this->assignPlan(
            company: $company,
            plan: $plan,
            billingCycle: BillingCycleEnum::Monthly,
        );
    }

    public function assignPlan(
        Company $company,
        Plan $plan,
        BillingCycleEnum $billingCycle,
        ?SuperAdmin $assignedBy = null,
        ?string $notes = null,
        ?\DateTimeInterface $trialEndsAt = null,
        ?\DateTimeInterface $endsAt = null,
    ): Subscription {
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => ['The selected plan is not active.'],
            ]);
        }

        return DB::transaction(function () use ($company, $plan, $billingCycle, $assignedBy, $notes, $trialEndsAt, $endsAt) {
            $this->cancelActiveSubscriptions($company, $assignedBy);

            $status = $trialEndsAt && $trialEndsAt > now()
                ? SubscriptionStatusEnum::Trialing
                : SubscriptionStatusEnum::Active;

            return Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'billing_cycle' => $billingCycle,
                'price' => $plan->priceForCycle($billingCycle),
                'starts_at' => now(),
                'ends_at' => $endsAt ?? match ($billingCycle) {
                    BillingCycleEnum::Monthly => now()->addMonth(),
                    BillingCycleEnum::Yearly => now()->addYear(),
                },
                'trial_ends_at' => $trialEndsAt,
                'assigned_by' => $assignedBy?->id,
                'notes' => $notes,
            ]);
        });
    }

    public function changePlan(
        Company $company,
        Plan $plan,
        BillingCycleEnum $billingCycle,
        ?SuperAdmin $assignedBy = null,
        ?string $notes = null,
        ?\DateTimeInterface $trialEndsAt = null,
        ?\DateTimeInterface $endsAt = null,
    ): Subscription {
        return $this->assignPlan(
            company: $company,
            plan: $plan,
            billingCycle: $billingCycle,
            assignedBy: $assignedBy,
            notes: $notes,
            trialEndsAt: $trialEndsAt,
            endsAt: $endsAt,
        );
    }

    public function cancel(Subscription $subscription, ?SuperAdmin $assignedBy = null, ?string $notes = null): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatusEnum::Cancelled,
            'cancelled_at' => now(),
            'notes' => $notes ?? $subscription->notes,
            'assigned_by' => $assignedBy?->id ?? $subscription->assigned_by,
        ]);

        return $subscription->fresh();
    }

    public function renew(Subscription $subscription, ?\DateTimeInterface $endsAt = null): Subscription
    {
        if (! in_array($subscription->status, [SubscriptionStatusEnum::Trialing, SubscriptionStatusEnum::Active], true)) {
            throw ValidationException::withMessages([
                'subscription' => ['Only active or trialing subscriptions can be renewed.'],
            ]);
        }

        $subscription->update([
            'ends_at' => $endsAt,
            'status' => SubscriptionStatusEnum::Active,
            'cancelled_at' => null,
        ]);

        return $subscription->fresh();
    }

    public function backfillDefaultPlans(): int
    {
        $defaultPlan = Plan::query()->default()->active()->first();

        if (! $defaultPlan) {
            return 0;
        }

        $count = 0;

        Company::query()
            ->whereDoesntHave('subscriptions', fn ($query) => $query->active())
            ->each(function (Company $company) use (&$count) {
                $this->assignDefaultPlan($company);
                $count++;
            });

        return $count;
    }

    private function cancelActiveSubscriptions(Company $company, ?SuperAdmin $assignedBy = null): void
    {
        Subscription::query()
            ->where('company_id', $company->id)
            ->active()
            ->each(function (Subscription $subscription) use ($assignedBy) {
                $this->cancel($subscription, $assignedBy, 'Replaced by a new subscription assignment.');
            });
    }
}

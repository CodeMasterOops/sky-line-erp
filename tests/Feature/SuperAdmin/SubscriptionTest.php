<?php

use App\Models\Plan;
use App\Models\Company;
use App\Models\SuperAdmin;
use App\Models\Subscription;
use App\Enums\BillingCycleEnum;
use App\Enums\SubscriptionStatusEnum;

function subscriptionSuperAdmin(): SuperAdmin
{
    return actingAsSuperAdmin();
}

function subscriptionCompany(): Company
{
    return Company::create([
        'company_name' => 'Test Co',
        'code' => 'TEST1',
        'email' => 'test@example.com',
        'is_active' => true,
    ]);
}

function subscriptionPlan(string $name = 'Basic', float $monthly = 49): Plan
{
    return Plan::create([
        'name' => $name,
        'slug' => strtolower($name),
        'price_monthly' => $monthly,
        'price_yearly' => $monthly * 10,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

it('assigns a subscription to a company', function () {
    subscriptionSuperAdmin();
    $company = subscriptionCompany();
    $plan = subscriptionPlan();

    $response = $this->postJson('/api/super-admin/subscription', [
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'billing_cycle' => BillingCycleEnum::Monthly->value,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.plan_id', $plan->id)
        ->assertJsonPath('data.price', '49.00')
        ->assertJsonPath('data.status', SubscriptionStatusEnum::Active->value);

    $this->assertDatabaseHas('subscriptions', [
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::Active->value,
    ]);
});

it('replaces the active subscription when assigning a new plan', function () {
    subscriptionSuperAdmin();
    $company = subscriptionCompany();
    $basic = subscriptionPlan('Basic', 49);
    $premium = subscriptionPlan('Premium', 199);

    $this->postJson('/api/super-admin/subscription', [
        'company_id' => $company->id,
        'plan_id' => $basic->id,
        'billing_cycle' => BillingCycleEnum::Monthly->value,
    ])->assertCreated();

    $this->postJson('/api/super-admin/subscription', [
        'company_id' => $company->id,
        'plan_id' => $premium->id,
        'billing_cycle' => BillingCycleEnum::Yearly->value,
    ])->assertCreated();

    expect(Subscription::query()->where('company_id', $company->id)->active()->count())->toBe(1);
    expect(Subscription::query()->where('company_id', $company->id)->where('status', SubscriptionStatusEnum::Cancelled)->count())->toBe(1);

    $active = Subscription::query()->where('company_id', $company->id)->active()->first();
    expect($active->plan_id)->toBe($premium->id);
    expect((float) $active->price)->toBe(1990.0);
});

it('cancels a subscription', function () {
    subscriptionSuperAdmin();
    $company = subscriptionCompany();
    $plan = subscriptionPlan();

    $create = $this->postJson('/api/super-admin/subscription', [
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'billing_cycle' => BillingCycleEnum::Monthly->value,
    ])->assertCreated();

    $subscriptionId = $create->json('data.id');

    $this->putJson("/api/super-admin/subscription/{$subscriptionId}/cancel", [
        'notes' => 'Customer requested cancellation',
    ])->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatusEnum::Cancelled->value);
});

it('renews an active subscription', function () {
    subscriptionSuperAdmin();
    $company = subscriptionCompany();
    $plan = subscriptionPlan();

    $create = $this->postJson('/api/super-admin/subscription', [
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'billing_cycle' => BillingCycleEnum::Monthly->value,
        'ends_at' => now()->addMonth()->toDateTimeString(),
    ])->assertCreated();

    $subscriptionId = $create->json('data.id');

    $this->putJson("/api/super-admin/subscription/{$subscriptionId}/renew", [
        'ends_at' => now()->addYear()->toDateTimeString(),
    ])->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatusEnum::Active->value);
});

it('lists subscriptions with filters', function () {
    subscriptionSuperAdmin();
    $company = subscriptionCompany();
    $plan = subscriptionPlan();

    Subscription::create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::Active,
        'billing_cycle' => BillingCycleEnum::Monthly,
        'price' => 49,
        'starts_at' => now(),
    ]);

    $this->getJson('/api/super-admin/subscription?company_id='.$company->id)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('requires authentication to manage subscriptions', function () {
    $this->getJson('/api/super-admin/subscription')->assertUnauthorized();
});

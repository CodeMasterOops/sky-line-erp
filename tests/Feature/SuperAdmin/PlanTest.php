<?php

use App\Models\Plan;

it('lists plans for authenticated super admin', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'price_monthly' => 49,
        'price_yearly' => 490,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson('/api/super-admin/plan');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('creates a plan', function () {
    actingAsSuperAdmin();

    $response = $this->postJson('/api/super-admin/plan', [
        'name' => 'Premium',
        'price_monthly' => 199,
        'price_yearly' => 1990,
        'features' => ['Unlimited users'],
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Premium')
        ->assertJsonPath('data.slug', 'premium');

    $this->assertDatabaseHas('plans', [
        'name' => 'Premium',
        'slug' => 'premium',
    ]);
});

it('updates a plan', function () {
    actingAsSuperAdmin();
    $plan = Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'price_monthly' => 49,
        'price_yearly' => 490,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->putJson("/api/super-admin/plan/{$plan->id}", [
        'name' => 'Basic Plus',
        'price_monthly' => 59,
        'price_yearly' => 590,
        'is_active' => true,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Basic Plus');
});

it('cannot delete the default plan', function () {
    actingAsSuperAdmin();
    $plan = createDefaultPlan();

    $this->deleteJson("/api/super-admin/plan/{$plan->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The default plan cannot be deleted.');
});

it('cannot delete a plan with subscriptions', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $paidPlan = Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'price_monthly' => 49,
        'price_yearly' => 490,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $company = \App\Models\Company::create([
        'company_name' => 'Test Co',
        'code' => 'TEST1',
        'email' => 'test@example.com',
    ]);

    \App\Models\Subscription::create([
        'company_id' => $company->id,
        'plan_id' => $paidPlan->id,
        'status' => \App\Enums\SubscriptionStatusEnum::Active,
        'billing_cycle' => \App\Enums\BillingCycleEnum::Monthly,
        'price' => 49,
        'starts_at' => now(),
    ]);

    $this->deleteJson("/api/super-admin/plan/{$paidPlan->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This plan cannot be deleted because it has subscriptions.');
});

it('requires authentication to manage plans', function () {
    $this->getJson('/api/super-admin/plan')->assertUnauthorized();
});

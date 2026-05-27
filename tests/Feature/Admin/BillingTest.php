<?php

use App\Models\Plan;
use App\Models\User;
use App\Models\Company;
use App\Enums\UserTypeEnum;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;
use App\Enums\BillingCycleEnum;
use App\Services\TenantService;
use App\Enums\SubscriptionStatusEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function billingCompanyWithUser(): array
{
    $company = Company::create([
        'company_name' => 'Billing Co',
        'code' => 'BILL1',
        'email' => 'billing@example.com',
        'is_active' => true,
    ]);

    $user = User::create([
        'company_id' => $company->id,
        'name' => 'Admin User',
        'email' => 'billing@example.com',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN->value,
        'status' => true,
    ]);

    return [$company, $user];
}

it('returns the current subscription for authenticated admin', function () {
    [$company, $user] = billingCompanyWithUser();

    $plan = Plan::create([
        'name' => 'Advanced',
        'slug' => 'advanced',
        'price_monthly' => 99,
        'price_yearly' => 990,
        'features' => ['Payroll'],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    Subscription::create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::Active,
        'billing_cycle' => BillingCycleEnum::Monthly,
        'price' => 99,
        'starts_at' => now(),
    ]);

    Sanctum::actingAs($user, [], 'admin');
    TenantService::setCompanyId($company->id);

    $response = $this->getJson('/api/admin/billing/subscription');

    $response->assertSuccessful()
        ->assertJsonPath('data.plan.name', 'Advanced')
        ->assertJsonPath('data.status', SubscriptionStatusEnum::Active->value);
});

it('returns null data when company has no active subscription', function () {
    [$company, $user] = billingCompanyWithUser();

    Sanctum::actingAs($user, [], 'admin');
    TenantService::setCompanyId($company->id);

    $this->getJson('/api/admin/billing/subscription')
        ->assertSuccessful()
        ->assertJsonPath('data', null);
});

it('returns available plans for authenticated admin', function () {
    [$company, $user] = billingCompanyWithUser();

    Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'description' => 'Starter',
        'price_monthly' => 999,
        'price_yearly' => 9999,
        'features' => ['1 branch location'],
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    Plan::create([
        'name' => 'Standard',
        'slug' => 'standard',
        'description' => 'Growth',
        'price_monthly' => 1999,
        'price_yearly' => 19999,
        'features' => ['2 branch locations'],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    Sanctum::actingAs($user, [], 'admin');
    TenantService::setCompanyId($company->id);

    $this->getJson('/api/admin/billing/plans')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Basic')
        ->assertJsonPath('data.0.features.0', '1 branch location');
});

it('requires authentication for billing endpoints', function () {
    $this->getJson('/api/admin/billing/subscription')->assertUnauthorized();
    $this->getJson('/api/admin/billing/plans')->assertUnauthorized();
});

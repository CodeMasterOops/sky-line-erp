<?php

use App\Models\Plan;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;
use App\Enums\BillingCycleEnum;
use App\Services\TenantService;
use App\Enums\SubscriptionStatusEnum;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::forget('allTables');

    $fiscalYear = FiscalYear::create([
        'year_name' => '2026BL',
        'year_code' => '26BL',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $fiscalYear->id,
        'company_name' => 'Branch Limit Co',
        'code' => 'BLC'.uniqid(),
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin User',
        'email' => 'admin-bl-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

it('blocks branch creation when plan branch limit is reached', function () {
    $plan = Plan::create([
        'name' => 'Basic',
        'slug' => 'basic-bl',
        'price_monthly' => 999,
        'price_yearly' => 9999,
        'branch_limit' => 1,
        'is_active' => true,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    Subscription::create([
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::Active,
        'billing_cycle' => BillingCycleEnum::Monthly,
        'price' => 999,
        'starts_at' => now(),
    ]);

    Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Head Office',
        'code' => 'HO',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/admin/branch', [
        'name' => 'Second Branch',
        'is_head_office' => false,
        'is_active' => true,
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Your current plan "Basic" allows a maximum of 1 branch(es). Please upgrade to add more branches.');
});

it('allows branch creation when under the plan branch limit', function () {
    $plan = Plan::create([
        'name' => 'Standard',
        'slug' => 'standard-bl',
        'price_monthly' => 1999,
        'price_yearly' => 19999,
        'branch_limit' => 2,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    Subscription::create([
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::Active,
        'billing_cycle' => BillingCycleEnum::Monthly,
        'price' => 1999,
        'starts_at' => now(),
    ]);

    Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Head Office',
        'code' => 'HO2',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/admin/branch', [
        'name' => 'Second Branch',
        'is_head_office' => false,
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Second Branch');
});

it('allows unlimited branches when plan has no branch limit', function () {
    $plan = Plan::create([
        'name' => 'Enterprise',
        'slug' => 'enterprise-bl',
        'price_monthly' => 9999,
        'price_yearly' => 99999,
        'branch_limit' => null,
        'is_active' => true,
        'sort_order' => 5,
    ]);

    Subscription::create([
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::Active,
        'billing_cycle' => BillingCycleEnum::Monthly,
        'price' => 9999,
        'starts_at' => now(),
    ]);

    foreach (range(1, 3) as $i) {
        Branch::create([
            'company_id' => $this->company->id,
            'name' => "Branch {$i}",
            'code' => "BR{$i}",
            'is_head_office' => $i === 1,
            'is_active' => true,
        ]);
    }

    $response = $this->postJson('/api/admin/branch', [
        'name' => 'Branch 4',
        'is_head_office' => false,
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Branch 4');
});

it('allows branch creation when company has no active subscription', function () {
    $response = $this->postJson('/api/admin/branch', [
        'name' => 'Free Branch',
        'is_head_office' => true,
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Free Branch');
});

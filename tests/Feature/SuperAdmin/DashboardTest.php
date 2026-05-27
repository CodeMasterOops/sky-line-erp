<?php

use App\Models\Plan;
use App\Models\Company;
use App\Models\Subscription;
use App\Enums\BillingCycleEnum;
use App\Enums\SubscriptionStatusEnum;

it('returns dashboard statistics for authenticated super admin', function () {
    actingAsSuperAdmin();

    $plan = Plan::create([
        'name' => 'Basic',
        'slug' => 'basic',
        'price_monthly' => 49,
        'price_yearly' => 490,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Company::create([
        'company_name' => 'Active Co',
        'code' => 'ACT001',
        'email' => 'active@example.com',
        'is_active' => true,
        'onboarding_completed_at' => now(),
    ]);

    $inactiveCompany = Company::create([
        'company_name' => 'Inactive Co',
        'code' => 'INA001',
        'email' => 'inactive@example.com',
        'is_active' => false,
    ]);

    Subscription::create([
        'company_id' => $inactiveCompany->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatusEnum::Active,
        'billing_cycle' => BillingCycleEnum::Monthly,
        'price' => 49,
        'starts_at' => now(),
    ]);

    $response = $this->getJson('/api/super-admin/dashboard');

    $response->assertSuccessful()
        ->assertJsonPath('total_companies', 2)
        ->assertJsonPath('active_companies', 1)
        ->assertJsonPath('inactive_companies', 1)
        ->assertJsonPath('onboarded_companies', 1)
        ->assertJsonPath('companies_today', 2)
        ->assertJsonPath('total_earnings', 49)
        ->assertJsonStructure([
            'total_companies',
            'active_companies',
            'inactive_companies',
            'onboarded_companies',
            'companies_today',
            'total_users',
            'fiscal_years_count',
            'total_earnings',
            'subscription_summary' => [
                'total_subscribers',
                'active_subscribers',
                'trialing_subscribers',
                'cancelled_this_month',
            ],
            'growth' => [
                'total_companies',
                'active_companies',
                'onboarded_companies',
                'total_earnings',
                'new_companies',
            ],
            'companies_from_last_month',
            'chart_data' => [
                'weekly' => ['labels', 'companies'],
                'monthly' => ['labels', 'new_companies', 'active_companies'],
                'sparklines' => ['total', 'active', 'onboarded', 'earnings'],
            ],
            'top_plans',
        ]);

    expect($response->json('chart_data.weekly.labels'))->toHaveCount(7);
    expect($response->json('chart_data.monthly.labels'))->toHaveCount(12);
    expect($response->json('top_plans.0.name'))->toBe('Basic');
    expect($response->json('top_plans.0.subscribers'))->toBe(1);
});

it('requires authentication to access dashboard', function () {
    $this->getJson('/api/super-admin/dashboard')
        ->assertUnauthorized();
});

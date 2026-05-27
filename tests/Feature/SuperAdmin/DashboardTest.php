<?php

use App\Models\Company;
use App\Models\SuperAdmin;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function actingAsSuperAdmin(): SuperAdmin
{
    $superAdmin = SuperAdmin::create([
        'name' => 'Super Admin',
        'email' => 'super@admin.com',
        'password' => 'password123',
    ]);

    Sanctum::actingAs($superAdmin, [], 'super_admin');

    return $superAdmin;
}

it('returns dashboard statistics for authenticated super admin', function () {
    actingAsSuperAdmin();
    Company::create([
        'company_name' => 'Active Co',
        'code' => 'ACT001',
        'email' => 'active@example.com',
        'is_active' => true,
        'onboarding_completed_at' => now(),
    ]);

    Company::create([
        'company_name' => 'Inactive Co',
        'code' => 'INA001',
        'email' => 'inactive@example.com',
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/super-admin/dashboard');

    $response->assertSuccessful()
        ->assertJsonPath('total_companies', 2)
        ->assertJsonPath('active_companies', 1)
        ->assertJsonPath('inactive_companies', 1)
        ->assertJsonPath('onboarded_companies', 1)
        ->assertJsonPath('companies_today', 2)
        ->assertJsonPath('total_earnings', 0)
        ->assertJsonStructure([
            'total_companies',
            'active_companies',
            'inactive_companies',
            'onboarded_companies',
            'companies_today',
            'total_users',
            'fiscal_years_count',
            'total_earnings',
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
});

it('requires authentication to access dashboard', function () {
    $this->getJson('/api/super-admin/dashboard')
        ->assertUnauthorized();
});

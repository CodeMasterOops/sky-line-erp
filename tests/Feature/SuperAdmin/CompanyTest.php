<?php

use App\Models\Ward;
use App\Models\Palika;
use App\Models\District;
use App\Models\Province;

function companyLocation(): Ward
{
    $province = Province::create(['name' => 'Bagmati']);
    $district = District::create(['province_id' => $province->id, 'name' => 'Kathmandu']);
    $palika = Palika::create(['district_id' => $district->id, 'name' => 'KMC']);

    return Ward::create(['palika_id' => $palika->id, 'name' => 'Ward 1']);
}

function validCompanyPayload(Ward $ward, array $overrides = []): array
{
    // Choosing an industry is mandatory: it decides the company's module set.
    $categoryId = App\Models\CompanyCategory::query()->value('id')
        ?? App\Models\CompanyCategory::factory()->default()->create()->id;

    return array_merge([
        'company_category_id' => $categoryId,
        'company_name' => 'New Company',
        'legal_name' => 'New Company Pvt. Ltd.',
        'code' => 'NC-01',
        'email' => 'newco@example.com',
        'address' => 'Thamel, Kathmandu',
        'ward_id' => $ward->id,
        'user_name' => 'Admin User',
        'user_email' => 'admin@newco.example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);
}

it('requires address fields when creating a company', function () {
    actingAsSuperAdmin();

    $response = $this->postJson('/api/super-admin/company', [
        'company_name' => 'New Company',
        'legal_name' => 'New Company Pvt. Ltd.',
        'code' => 'NC-02',
        'email' => 'missing-address@example.com',
        'user_name' => 'Admin User',
        'user_email' => 'admin2@newco.example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['address', 'ward_id']);
});

it('requires an industry category when creating a company', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $ward = companyLocation();

    $payload = validCompanyPayload($ward);
    unset($payload['company_category_id']);

    $this->postJson('/api/super-admin/company', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_category_id');
});

it('rejects an industry category that does not exist', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $ward = companyLocation();

    $this->postJson('/api/super-admin/company', validCompanyPayload($ward, [
        'company_category_id' => 99999,
    ]))->assertUnprocessable()->assertJsonValidationErrors('company_category_id');
});

it('starts the company on its category\'s modules', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $ward = companyLocation();

    $gym = App\Models\CompanyCategory::factory()
        ->withModules(['accounting', 'inventory', 'sales', 'gym'])
        ->create(['slug' => 'gym-test']);

    $response = $this->postJson('/api/super-admin/company', validCompanyPayload($ward, [
        'company_category_id' => $gym->id,
    ]))->assertCreated();

    $companyId = $response->json('data.id');

    expect($response->json('data.category_name'))->toBe($gym->name)
        ->and(app(App\Services\Modules\CompanyModuleService::class)->enabledKeys($companyId))
        ->toContain('gym');
});

it('creates a company with required address fields', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $ward = companyLocation();

    $response = $this->postJson('/api/super-admin/company', validCompanyPayload($ward));

    $response->assertCreated()
        ->assertJsonPath('data.address', 'Thamel, Kathmandu')
        ->assertJsonPath('data.ward_id', $ward->id);

    $this->assertDatabaseHas('companies', [
        'email' => 'newco@example.com',
        'address' => 'Thamel, Kathmandu',
        'ward_id' => $ward->id,
    ]);
});

it('marks onboarding complete when a company is created with full details', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $ward = companyLocation();

    $create = $this->postJson('/api/super-admin/company', validCompanyPayload($ward, [
        'code' => 'OC-01',
        'email' => 'onboardedco@example.com',
        'user_email' => 'admin@onboardedco.example.com',
    ]));

    $create->assertCreated();

    $company = \App\Models\Company::find($create->json('data.id'));
    expect($company->onboarding_completed_at)->not->toBeNull();
});

it('allows super admin to login as a company admin without forcing onboarding', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $ward = companyLocation();

    $create = $this->postJson('/api/super-admin/company', validCompanyPayload($ward, [
        'code' => 'LC-01',
        'email' => 'loginco@example.com',
        'user_email' => 'admin@loginco.example.com',
    ]));
    $create->assertCreated();
    $companyId = $create->json('data.id');

    $response = $this->postJson("/api/super-admin/company/{$companyId}/login");

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'expires_at', 'user', 'permissions', 'message'])
        ->assertJsonPath('needs_onboarding', false);
});

it('rejects company login when company is inactive', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $ward = companyLocation();

    $create = $this->postJson('/api/super-admin/company', validCompanyPayload($ward, [
        'code' => 'IC-01',
        'email' => 'inactiveco@example.com',
        'user_email' => 'admin@inactiveco.example.com',
    ]));
    $create->assertCreated();
    $companyId = $create->json('data.id');

    $this->putJson("/api/super-admin/company/{$companyId}/update-status");

    $response = $this->postJson("/api/super-admin/company/{$companyId}/login");

    $response->assertStatus(400);
});

it('requires address fields when updating a company', function () {
    actingAsSuperAdmin();
    createDefaultPlan();
    $ward = companyLocation();

    $create = $this->postJson('/api/super-admin/company', validCompanyPayload($ward, [
        'code' => 'UC-01',
        'email' => 'updateco@example.com',
        'user_email' => 'admin@updateco.example.com',
    ]));
    $create->assertCreated();
    $companyId = $create->json('data.id');

    $response = $this->putJson("/api/super-admin/company/{$companyId}", [
        'company_name' => 'Updated Company',
        'legal_name' => 'Updated Company Pvt. Ltd.',
        'code' => 'UC-01',
        'email' => 'updateco@example.com',
        'user_name' => 'Admin User',
        'user_email' => 'admin@updateco.example.com',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['address', 'ward_id']);
});

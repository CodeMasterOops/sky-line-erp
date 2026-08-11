<?php

use App\Models\User;
use App\Models\Ward;
use App\Models\Palika;
use App\Models\Company;
use App\Models\District;
use App\Models\Province;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);

    $this->company = Company::create([
        'company_name' => 'Onboard Co',
        'code' => 'ONBCO',
        'email' => 'onboard@example.com',
        'is_active' => true,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Onboard User',
        'email' => 'onboard@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN->value,
    ]);

    $province = Province::create(['name' => 'Bagmati']);
    $district = District::create(['province_id' => $province->id, 'name' => 'Kathmandu']);
    $palika = Palika::create(['district_id' => $district->id, 'name' => 'KMC']);
    $this->ward = Ward::create(['palika_id' => $palika->id, 'name' => 'Ward 1']);
});

it('marks onboarding as complete and returns needs_onboarding false', function () {
    Sanctum::actingAs($this->user, ['*'], 'admin');

    expect($this->company->onboarding_completed_at)->toBeNull();

    $this->postJson('/api/admin/onboarding/complete')
        ->assertOk()
        ->assertJsonFragment(['needs_onboarding' => false]);

    expect($this->company->fresh()->onboarding_completed_at)->not->toBeNull();
});

it('requires authentication to complete onboarding', function () {
    $this->postJson('/api/admin/onboarding/complete')
        ->assertUnauthorized();
});

it('login response includes needs_onboarding true when not completed', function () {
    $this->postJson('/api/admin/login', [
        'email' => 'onboard@example.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonFragment(['needs_onboarding' => true]);
});

it('login response includes needs_onboarding false when already completed', function () {
    $this->company->update(['onboarding_completed_at' => now()]);

    $this->postJson('/api/admin/login', [
        'email' => 'onboard@example.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonFragment(['needs_onboarding' => false]);
});

it('updates company details during onboarding', function () {
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $this->putJson('/api/admin/onboarding/company', [
        'legal_name' => 'Onboard Co Pvt. Ltd.',
        'phone' => '+977-01-1234567',
        'pan' => '123456789',
        'ward_id' => $this->ward->id,
        'address' => 'New Road, Kathmandu',
        // Choosing an industry is part of onboarding — it decides the modules
        // the workspace starts with.
        'company_category_id' => App\Models\CompanyCategory::query()->value('id'),
    ])->assertOk()
        ->assertJsonFragment(['message' => 'Company details updated.']);

    expect($this->company->fresh()->legal_name)->toBe('Onboard Co Pvt. Ltd.');
    expect($this->company->fresh()->phone)->toBe('+977-01-1234567');
    expect($this->company->fresh()->pan)->toBe('123456789');
    expect($this->company->fresh()->ward_id)->toBe($this->ward->id);
});

it('requires phone pan ward and address to update company during onboarding', function () {
    Sanctum::actingAs($this->user, ['*'], 'admin');

    $this->putJson('/api/admin/onboarding/company', [
        'legal_name' => 'Missing required fields',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['phone', 'pan', 'ward_id', 'address', 'company_category_id']);
});

it('requires authentication to update onboarding company details', function () {
    $this->putJson('/api/admin/onboarding/company', [
        'legal_name' => 'Test',
    ])->assertUnauthorized();
});

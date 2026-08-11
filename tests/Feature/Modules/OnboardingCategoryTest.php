<?php

use App\Models\User;
use App\Models\Ward;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use Database\Seeders\CompanyCategorySeeder;
use App\Services\Modules\CompanyModuleService;

/*
| Choosing an industry is mandatory — it is what decides the module set a
| company starts with, so leaving it to a fallback means somebody has to go and
| correct the modules afterwards.
*/

beforeEach(function () {
    $this->seed(CompanyCategorySeeder::class);

    $this->company = makeCompany('Acme Fitness', 'ACME');

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Owner',
        'email' => 'owner@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, [], 'admin');

    $province = App\Models\Province::create(['name' => 'Bagmati']);
    $district = App\Models\District::create(['province_id' => $province->id, 'name' => 'Kathmandu']);
    $palika = App\Models\Palika::create(['district_id' => $district->id, 'name' => 'KMC']);
    $this->ward = Ward::create(['palika_id' => $palika->id, 'name' => 'Ward 1']);

    $this->payload = fn (array $overrides = []): array => array_merge([
        'phone' => '9800000000',
        'pan' => '123456789',
        'address' => 'Thamel, Kathmandu',
        'ward_id' => $this->ward->id,
    ], $overrides);
});

it('offers the industry catalogue to the onboarding form', function () {
    $categories = $this->getJson('/api/admin/onboarding/category')->assertSuccessful()->json('data');

    expect(collect($categories)->pluck('slug'))->toContain('gym')->toContain('retail')
        ->and(collect($categories)->firstWhere('slug', 'general')['is_default'])->toBeTruthy();
});

it('will not complete the company step without an industry', function () {
    $this->putJson('/api/admin/onboarding/company', ($this->payload)())
        ->assertStatus(422)
        ->assertJsonValidationErrors('company_category_id');
});

it('rejects an industry that does not exist', function () {
    $this->putJson('/api/admin/onboarding/company', ($this->payload)(['company_category_id' => 99999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('company_category_id');
});

it('switches on the chosen industry\'s modules', function () {
    $gym = CompanyCategory::query()->where('slug', 'gym')->sole();

    $this->putJson('/api/admin/onboarding/company', ($this->payload)([
        'company_category_id' => $gym->id,
    ]))->assertSuccessful();

    expect($this->company->fresh()->company_category_id)->toBe($gym->id)
        ->and(app(CompanyModuleService::class)->enabledKeys($this->company->id))->toContain('gym');
});

it('gives a retail company the point of sale instead', function () {
    $retail = CompanyCategory::query()->where('slug', 'retail')->sole();

    $this->putJson('/api/admin/onboarding/company', ($this->payload)([
        'company_category_id' => $retail->id,
    ]))->assertSuccessful();

    $enabled = app(CompanyModuleService::class)->enabledKeys($this->company->id);

    expect($enabled)->toContain('pos')
        ->and($enabled)->not->toContain('gym');
});

it('reports the chosen industry back to the form', function () {
    $gym = CompanyCategory::query()->where('slug', 'gym')->sole();

    $this->putJson('/api/admin/onboarding/company', ($this->payload)([
        'company_category_id' => $gym->id,
    ]))->assertSuccessful();

    $setting = $this->getJson('/api/admin/setting')->assertSuccessful()->json('data');

    expect($setting['company_category_id'])->toBe($gym->id)
        ->and($setting['category_name'])->toBe($gym->name);
});

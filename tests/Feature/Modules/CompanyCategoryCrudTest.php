<?php

use App\Models\CompanyCategory;
use Database\Seeders\CompanyCategorySeeder;

/*
| Phase 4 — the Super Admin manages the industry catalogue.
|
| A category is a starting point, not a rule: editing one never rewrites the
| companies already assigned to it. Applying defaults to a live company is a
| separate, deliberate action.
*/

beforeEach(function () {
    actingAsSuperAdmin();
});

it('lists categories with their module defaults', function () {
    $this->seed(CompanyCategorySeeder::class);

    $response = $this->getJson('/api/super-admin/company-category')->assertSuccessful();

    $retail = collect($response->json('data'))->firstWhere('slug', 'retail');

    expect($retail['modules'])->toContain('pos')
        ->and($retail['companies_count'])->toBe(0);
});

it('creates a category', function () {
    $response = $this->postJson('/api/super-admin/company-category', [
        'name' => 'Salon & Spa',
        'description' => 'Appointment-driven beauty businesses.',
        'icon' => 'ti ti-scissors',
        'modules' => ['crm'],
    ])->assertSuccessful();

    expect($response->json('data.slug'))->toBe('salon-spa')
        ->and(CompanyCategory::query()->where('slug', 'salon-spa')->exists())->toBeTrue();
});

it('closes the module selection over its requirements', function () {
    // Choosing `sales` without `inventory` would produce a category whose sales
    // module the resolver switches straight back off.
    $response = $this->postJson('/api/super-admin/company-category', [
        'name' => 'Trading',
        'modules' => ['sales'],
    ])->assertSuccessful();

    expect($response->json('data.modules'))
        ->toContain('sales')
        ->toContain('accounting')
        ->toContain('inventory');
});

it('rejects a module that does not exist', function () {
    $this->postJson('/api/super-admin/company-category', [
        'name' => 'Bogus',
        'modules' => ['teleportation'],
    ])->assertStatus(422)->assertJsonValidationErrors('modules.0');
});

it('rejects a duplicate slug', function () {
    $existing = CompanyCategory::factory()->create();

    $this->postJson('/api/super-admin/company-category', [
        'name' => 'Copycat',
        'slug' => $existing->slug,
    ])->assertStatus(422)->assertJsonValidationErrors('slug');
});

it('replaces the module set on update', function () {
    $category = CompanyCategory::factory()->withModules(['crm', 'hr'])->create();

    $response = $this->putJson("/api/super-admin/company-category/{$category->id}", [
        'name' => $category->name,
        'modules' => ['crm'],
    ])->assertSuccessful();

    expect($response->json('data.modules'))->toBe(['crm']);
});

it('keeps only one default category', function () {
    $first = CompanyCategory::factory()->create(['is_default' => true]);
    $second = CompanyCategory::factory()->create();

    $this->putJson("/api/super-admin/company-category/{$second->id}", [
        'name' => $second->name,
        'is_default' => true,
    ])->assertSuccessful();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('refuses to delete the default category', function () {
    $category = CompanyCategory::factory()->create(['is_default' => true]);

    $this->deleteJson("/api/super-admin/company-category/{$category->id}")->assertStatus(422);

    expect(CompanyCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('refuses to delete a category companies are assigned to', function () {
    $category = CompanyCategory::factory()->create();
    makeCompany('Acme', 'ACME')->update(['company_category_id' => $category->id]);

    $this->deleteJson("/api/super-admin/company-category/{$category->id}")->assertStatus(422);
});

it('deletes an unused category', function () {
    $category = CompanyCategory::factory()->create();

    $this->deleteJson("/api/super-admin/company-category/{$category->id}")->assertSuccessful();

    expect(CompanyCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('does not touch companies already in the category when its defaults change', function () {
    $category = CompanyCategory::factory()->withModules(['crm'])->create();
    $company = makeCompany('Acme', 'ACME');
    $company->update(['company_category_id' => $category->id]);

    $service = app(App\Services\Modules\CompanyModuleService::class);
    expect($service->isEnabled('crm', $company->id))->toBeTrue();

    $this->putJson("/api/super-admin/company-category/{$category->id}", [
        'name' => $category->name,
        'modules' => ['hr'],
    ])->assertSuccessful();

    // The company follows its category while it has no explicit row of its own;
    // what matters is that editing the catalogue did not write anything to the
    // company or raise an audit event behind the Super Admin's back.
    expect(App\Models\CompanyModuleEvent::query()->where('company_id', $company->id)->count())->toBe(0);
});

<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\CompanyCategory;
use Illuminate\Support\Facades\Bus;
use App\Services\Modules\ModuleCache;
use App\Jobs\ReconcileCompanyModulesJob;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 4 — propagation & cache correctness
| (docs/module-capping-and-advanced-handling-plan.md gaps D1–D4).
|
| The resolution is cached with `forever` and invalidated by row writes, so any
| change that touches no company_modules row — a category edit, a plan edit, a
| deploy — used to change nothing at all for the companies it was aimed at.
*/

beforeEach(function () {
    $this->service = app(CompanyModuleService::class);
    $this->cache = app(ModuleCache::class);

    $this->category = CompanyCategory::factory()->withModules(['accounting', 'inventory'])->create();

    $this->company = makeCompany('Follower', 'FOLLOW');
    $this->company->update(['company_category_id' => $this->category->id]);
    $this->company->refresh();

    $this->superAdmin = actingAsSuperAdmin();
});

it('shows a category edit to the companies that follow it', function () {
    // No explicit rows: this company resolves entirely through its category.
    expect($this->service->enabledKeys((int) $this->company->id))->not->toContain('crm');

    $this->putJson("/api/super-admin/company-category/{$this->category->id}", [
        'name' => $this->category->name,
        'modules' => ['accounting', 'inventory', 'crm'],
    ])->assertSuccessful();

    expect($this->service->enabledKeys((int) $this->company->id))->toContain('crm');
});

it('leaves a company with explicit rows untouched by a category edit', function () {
    // Explicit rows outrank the category by design — the edit must not reach in
    // and rewrite a decision somebody made for this company.
    $this->service->disable($this->company, 'crm');

    $this->putJson("/api/super-admin/company-category/{$this->category->id}", [
        'name' => $this->category->name,
        'modules' => ['accounting', 'inventory', 'crm'],
    ])->assertSuccessful();

    expect($this->service->enabledKeys((int) $this->company->id))->not->toContain('crm');
});

it('invalidates every company when the shipped registry changes', function () {
    $before = $this->service->enabledKeys((int) $this->company->id);
    $key = ModuleCache::keyFor((int) $this->company->id);

    expect(Cache::has($key))->toBeTrue();

    // Simulate a deploy that ships a module: the fingerprint changes, so the
    // old entry can no longer be read under the new key.
    config()->set('modules.a-new-module', [
        'name' => 'A New Module',
        'group' => 'optional',
        'requires' => [],
        'permissions' => [],
    ]);
    app(ModuleRegistry::class)->flush();

    expect(ModuleCache::keyFor((int) $this->company->id))->not->toBe($key)
        ->and(Cache::has(ModuleCache::keyFor((int) $this->company->id)))->toBeFalse()
        ->and($this->service->enabledKeys((int) $this->company->id))->toEqualCanonicalizing($before);
});

it('reconciles every subscriber when a plan\'s module list changes', function () {
    Bus::fake();

    $plan = Plan::factory()->create(['modules' => ['core', 'accounting', 'inventory']]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    $this->putJson("/api/super-admin/plan/{$plan->id}", [
        'name' => $plan->name,
        'slug' => $plan->slug,
        'price_monthly' => 100,
        'price_yearly' => 1000,
        'modules' => ['core', 'accounting'],
    ])->assertSuccessful()->assertJsonPath('reconciled_companies', 1);

    Bus::assertDispatched(ReconcileCompanyModulesJob::class);
});

it('does not reconcile when a plan edit leaves entitlements alone', function () {
    Bus::fake();

    $plan = Plan::factory()->create(['modules' => ['core', 'accounting']]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    $this->putJson("/api/super-admin/plan/{$plan->id}", [
        'name' => 'Renamed',
        'slug' => $plan->slug,
        'price_monthly' => 100,
        'price_yearly' => 1000,
        'modules' => ['core', 'accounting'],
    ])->assertSuccessful()->assertJsonPath('reconciled_companies', 0);

    Bus::assertNotDispatched(ReconcileCompanyModulesJob::class);
});

it('flushes every cached company on demand', function () {
    $this->service->enabledKeys((int) $this->company->id);

    expect(Cache::has(ModuleCache::keyFor((int) $this->company->id)))->toBeTrue();

    $this->artisan('modules:flush-cache')->assertSuccessful();

    expect(Cache::has(ModuleCache::keyFor((int) $this->company->id)))->toBeFalse();
});

<?php

use App\Models\Plan;
use App\Models\CompanyModule;
use App\Enums\BillingCycleEnum;
use App\Models\CompanyCategory;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ReconcileCompanyModulesJob;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 4 — plans cap what a company may run
| (docs/saas-modular-platform-and-gym-module-plan.md §3.5 step 5).
|
| The rule that matters commercially: a downgrade HIDES modules, it never
| deletes their data, so an upgrade restores everything exactly as it was. A
| plan with no module list is uncapped, which is what every plan that predates
| entitlements stays.
*/

beforeEach(function () {
    $this->superAdmin = actingAsSuperAdmin();
    $this->company = makeCompany('Acme Fitness', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales', 'crm'])->create()->id,
    ]);
    $this->company->refresh();

    $this->service = app(CompanyModuleService::class);
});

it('accepts a module entitlement list on a plan', function () {
    $response = $this->postJson('/api/super-admin/plan', [
        'name' => 'Growth',
        'price_monthly' => 1999,
        'price_yearly' => 19999,
        'modules' => ['core', 'accounting', 'inventory', 'sales'],
    ])->assertSuccessful();

    expect($response->json('data.modules'))->toContain('sales')
        ->and(Plan::query()->where('name', 'Growth')->sole()->modules)->toContain('accounting');
});

it('rejects an entitlement for a module that does not exist', function () {
    $this->postJson('/api/super-admin/plan', [
        'name' => 'Bogus',
        'price_monthly' => 100,
        'price_yearly' => 1000,
        'modules' => ['core', 'teleportation'],
    ])->assertStatus(422)->assertJsonValidationErrors('modules.1');
});

it('treats a plan with no list as uncapped', function () {
    $plan = Plan::factory()->create(['modules' => null]);

    expect($plan->entitlesModule('gym'))->toBeTrue()
        ->and($plan->entitlesModule('anything-at-all'))->toBeTrue();
});

it('queues a reconciliation when a plan is assigned', function () {
    Queue::fake();

    $plan = Plan::factory()->create(['modules' => ['core', 'accounting']]);

    app(SubscriptionService::class)->assignPlan($this->company, $plan, BillingCycleEnum::Monthly);

    Queue::assertPushed(ReconcileCompanyModulesJob::class, fn ($job): bool => $job->company->is($this->company));
});

it('hides the modules a downgrade no longer covers', function () {
    $this->service->enable($this->company, 'crm', $this->superAdmin, source: App\Enums\ModuleSourceEnum::Category);

    $basic = Plan::factory()->create(['name' => 'Basic', 'modules' => ['core', 'accounting']]);
    app(SubscriptionService::class)->assignPlan($this->company, $basic, BillingCycleEnum::Monthly);

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'accounting'])
        ->and($this->service->isEnabled('crm', $this->company->id))->toBeFalse();
});

it('deletes nothing when a plan is downgraded', function () {
    $this->service->enable($this->company, 'crm', $this->superAdmin, source: App\Enums\ModuleSourceEnum::Category);
    $this->service->updateSettings($this->company, 'crm', ['reminder_hour' => 9], $this->superAdmin);

    $keysBefore = CompanyModule::query()->where('company_id', $this->company->id)->pluck('module_key')->all();

    $basic = Plan::factory()->create(['modules' => ['core', 'accounting']]);
    app(SubscriptionService::class)->assignPlan($this->company, $basic, BillingCycleEnum::Monthly);

    // Reconciliation *adds* rows — it writes the cap down explicitly — but it
    // must never remove one, and the module's settings have to survive intact
    // so an upgrade restores it untouched.
    $keysAfter = CompanyModule::query()->where('company_id', $this->company->id)->pluck('module_key')->all();

    expect(array_diff($keysBefore, $keysAfter))->toBe([])
        ->and(CompanyModule::query()->where('module_key', 'crm')->sole()->settings)->toBe(['reminder_hour' => 9]);
});

it('restores everything on an upgrade', function () {
    $basic = Plan::factory()->create(['modules' => ['core', 'accounting']]);
    app(SubscriptionService::class)->assignPlan($this->company, $basic, BillingCycleEnum::Monthly);

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'accounting']);

    $full = Plan::factory()->create(['modules' => null]);
    app(SubscriptionService::class)->assignPlan($this->company, $full, BillingCycleEnum::Monthly);

    expect($this->service->enabledKeys($this->company->id))
        ->toContain('sales')
        ->toContain('crm')
        ->toContain('inventory');
});

it('leaves a deliberate super admin override in place through a downgrade', function () {
    $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['hr' => true],
        'reason' => 'Agreed as part of their contract.',
    ])->assertSuccessful();

    $basic = Plan::factory()->create(['modules' => ['core', 'accounting']]);
    app(SubscriptionService::class)->assignPlan($this->company, $basic, BillingCycleEnum::Monthly);

    expect($this->service->isEnabled('hr', $this->company->id))->toBeTrue();
});

it('never caps the always-on core module', function () {
    $basic = Plan::factory()->create(['modules' => []]);
    app(SubscriptionService::class)->assignPlan($this->company, $basic, BillingCycleEnum::Monthly);

    expect($this->service->isEnabled('core', $this->company->id))->toBeTrue();
});

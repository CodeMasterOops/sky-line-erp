<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\CompanyModule;
use App\Enums\ModuleSourceEnum;
use App\Models\CompanyCategory;
use App\Models\CompanyModuleEvent;
use App\Enums\ModuleEventActionEnum;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 4 — the Super Admin control surface
| (docs/saas-modular-platform-and-gym-module-plan.md §3.11).
|
| The Super Admin has the final say over what a company runs: category defaults
| are a starting point, the plan is a cap, and a manual decision here outranks
| both. What none of it can do is destroy tenant data.
*/

beforeEach(function () {
    $this->superAdmin = actingAsSuperAdmin();
    $this->company = makeCompany('Acme Fitness', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales'])->create()->id,
    ]);
    $this->company->refresh();

    $this->service = app(CompanyModuleService::class);
});

it('lists the module catalogue', function () {
    $response = $this->getJson('/api/super-admin/module')->assertSuccessful();

    expect($response->json('data'))->toHaveCount(count(app(ModuleRegistry::class)->keys()))
        ->and($response->json('meta.always_on'))->toBe(['core'])
        ->and(collect($response->json('data'))->firstWhere('key', 'sales')['requires'])
        ->toEqualCanonicalizing(['accounting', 'inventory']);
});

it('shows the matrix for one company', function () {
    $response = $this->getJson("/api/super-admin/company/{$this->company->id}/module")->assertSuccessful();

    $sales = collect($response->json('data'))->firstWhere('key', 'sales');
    $crm = collect($response->json('data'))->firstWhere('key', 'crm');

    expect($sales['enabled'])->toBeTrue()
        ->and($sales['is_category_default'])->toBeTrue()
        ->and($crm['enabled'])->toBeFalse()
        ->and($crm['is_category_default'])->toBeFalse()
        ->and($response->json('meta.category.slug'))->not->toBeNull();
});

it('marks core as locked so the UI cannot offer to disable it', function () {
    $core = collect($this->getJson("/api/super-admin/company/{$this->company->id}/module")->json('data'))
        ->firstWhere('key', 'core');

    expect($core['locked'])->toBeTrue();
});

it('enables a module and everything it requires', function () {
    $response = $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['manufacturing' => true],
        'reason' => 'Customer started assembling kits.',
    ])->assertSuccessful();

    expect($response->json('data.modules'))->toContain('manufacturing')
        ->and($this->service->isEnabled('manufacturing', $this->company->id))->toBeTrue();
});

it('records who made the change and why', function () {
    $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['crm' => true],
        'reason' => 'Included in their upgrade.',
    ])->assertSuccessful();

    $event = CompanyModuleEvent::query()->where('module_key', 'crm')->sole();

    expect($event->action)->toBe(ModuleEventActionEnum::Enabled)
        ->and($event->reason)->toBe('Included in their upgrade.')
        ->and($event->actor_id)->toBe($this->superAdmin->id);
});

it('refuses to disable a module other enabled modules need', function () {
    $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['inventory' => false],
    ])->assertStatus(422);

    expect($this->service->isEnabled('inventory', $this->company->id))->toBeTrue();
});

it('disables dependents too when asked to cascade', function () {
    $response = $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['inventory' => false],
        'cascade' => true,
    ])->assertSuccessful();

    expect($response->json('data.disabled'))->toContain('inventory')->toContain('sales')
        ->and($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'accounting']);
});

it('silently leaves core alone rather than failing the whole batch', function () {
    $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['core' => false, 'crm' => true],
    ])->assertSuccessful();

    expect($this->service->isEnabled('core', $this->company->id))->toBeTrue()
        ->and($this->service->isEnabled('crm', $this->company->id))->toBeTrue();
});

it('rejects a module key that does not exist', function () {
    $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['not-a-module' => true],
    ])->assertStatus(422)->assertJsonValidationErrors('modules');
});

it('keeps the tenant\'s data when a module is switched off', function () {
    $this->service->enable($this->company, 'crm');
    $rowsBefore = CompanyModule::query()->where('company_id', $this->company->id)->count();

    $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['crm' => false],
    ])->assertSuccessful();

    expect(CompanyModule::query()->where('company_id', $this->company->id)->count())->toBe($rowsBefore)
        ->and(CompanyModule::query()->where('module_key', 'crm')->sole()->is_enabled)->toBeFalse();
});

it('moves a company to a different category and applies its defaults', function () {
    $gym = CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales', 'crm'])->create(['slug' => 'gym']);

    $response = $this->putJson("/api/super-admin/company/{$this->company->id}/category", [
        'company_category_id' => $gym->id,
    ])->assertSuccessful();

    expect($response->json('data.changed'))->toContain('crm')
        ->and($this->company->fresh()->company_category_id)->toBe($gym->id);
});

it('does not take modules away when the category changes', function () {
    $this->service->enable($this->company, 'hr');
    $narrow = CompanyCategory::factory()->withModules(['accounting'])->create();

    $this->putJson("/api/super-admin/company/{$this->company->id}/category", [
        'company_category_id' => $narrow->id,
    ])->assertSuccessful();

    expect($this->service->isEnabled('hr', $this->company->id))->toBeTrue()
        ->and($this->service->isEnabled('sales', $this->company->id))->toBeTrue();
});

it('drops the extras only when asked to', function () {
    $this->service->enable($this->company, 'hr');
    $narrow = CompanyCategory::factory()->withModules(['accounting'])->create();

    $this->putJson("/api/super-admin/company/{$this->company->id}/category", [
        'company_category_id' => $narrow->id,
        'disable_others' => true,
    ])->assertSuccessful();

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'accounting']);
});

it('resets a company back to its category defaults', function () {
    $this->service->enable($this->company, 'hr');

    $this->postJson("/api/super-admin/company/{$this->company->id}/module/reset")->assertSuccessful();

    expect($this->service->enabledKeys($this->company->id))
        ->toEqualCanonicalizing(['core', 'accounting', 'inventory', 'sales']);
});

it('refuses to reset a company that has no category', function () {
    $this->company->update(['company_category_id' => null]);

    $this->postJson("/api/super-admin/company/{$this->company->id}/module/reset")->assertStatus(422);
});

it('serves the audit trail newest first', function () {
    $this->service->enable($this->company, 'crm', $this->superAdmin, 'First');
    $this->service->disable($this->company, 'crm', $this->superAdmin, 'Second');

    $events = $this->getJson("/api/super-admin/company/{$this->company->id}/module/event")
        ->assertSuccessful()
        ->json('data');

    expect($events[0]['reason'])->toBe('Second')
        ->and($events[0]['action'])->toBe('disabled')
        ->and($events[0]['module_name'])->toBe('CRM')
        ->and($events[1]['reason'])->toBe('First');
});

it('filters the audit trail by module', function () {
    $this->service->enable($this->company, 'crm', $this->superAdmin);
    $this->service->enable($this->company, 'hr', $this->superAdmin);

    $events = $this->getJson("/api/super-admin/company/{$this->company->id}/module/event?module_key=hr")
        ->assertSuccessful()
        ->json('data');

    expect($events)->toHaveCount(1)
        ->and($events[0]['module_key'])->toBe('hr');
});

it('shows which modules the plan does not cover', function () {
    $plan = Plan::factory()->create(['name' => 'Basic', 'modules' => ['core', 'accounting']]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    $data = collect($this->getJson("/api/super-admin/company/{$this->company->id}/module")->json('data'));

    expect($data->firstWhere('key', 'sales')['entitled_by_plan'])->toBeFalse()
        ->and($data->firstWhere('key', 'accounting')['entitled_by_plan'])->toBeTrue();
});

it('lets a super admin override the plan cap on purpose', function () {
    $plan = Plan::factory()->create(['name' => 'Basic', 'modules' => ['core', 'accounting']]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    $this->putJson("/api/super-admin/company/{$this->company->id}/module", [
        'modules' => ['crm' => true],
        'reason' => 'Goodwill during migration.',
    ])->assertSuccessful();

    expect($this->service->isEnabled('crm', $this->company->id))->toBeTrue()
        ->and(CompanyModule::query()->where('module_key', 'crm')->sole()->source)->toBe(ModuleSourceEnum::Manual);
});

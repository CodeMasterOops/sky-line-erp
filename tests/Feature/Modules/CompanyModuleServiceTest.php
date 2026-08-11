<?php

use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\Subscription;
use App\Models\CompanyModule;
use App\Enums\ModuleSourceEnum;
use App\Models\CompanyCategory;
use App\Models\CompanyModuleEvent;
use App\Enums\ModuleEventActionEnum;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;
use Illuminate\Validation\ValidationException;

/*
| Phase 1 — the write side: enabling, disabling, category sync, plan
| reconciliation and the audit trail. The guarantee under test throughout is
| that switching a module never destroys anything: rows keep existing, settings
| survive, and re-enabling restores the previous state.
*/

beforeEach(function () {
    $this->service = app(CompanyModuleService::class);
    $this->company = makeCompany('Acme Fitness', 'ACME');

    // Give the company a category with no defaults so it counts as *configured*
    // with nothing switched on. Without a category and without rows it would sit
    // in the pre-modular fallback (everything on) — correct for a legacy tenant,
    // but useless as a starting point for asserting individual transitions.
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules([])->create()->id,
    ]);
    $this->company->refresh();

    $this->actor = SuperAdmin::create([
        'name' => 'Super Admin',
        'email' => 'super@admin.com',
        'password' => 'password123',
    ]);
});

it('enables a module together with everything it requires', function () {
    $enabled = $this->service->enable($this->company, 'sales', $this->actor);

    expect($enabled)->toEqualCanonicalizing(['accounting', 'inventory', 'sales'])
        ->and($this->service->enabledKeys($this->company->id))
        ->toEqualCanonicalizing(['core', 'accounting', 'inventory', 'sales']);
});

it('records who enabled a module and why', function () {
    $this->service->enable($this->company, 'crm', $this->actor, 'Customer asked for it.');

    $event = CompanyModuleEvent::query()->where('module_key', 'crm')->sole();

    expect($event->action)->toBe(ModuleEventActionEnum::Enabled)
        ->and($event->reason)->toBe('Customer asked for it.')
        ->and($event->actor_id)->toBe($this->actor->id)
        ->and($event->actor_type)->toBe(SuperAdmin::class)
        ->and($event->context['from'])->toBeNull()
        ->and($event->context['to'])->toBeTrue();

    $row = CompanyModule::query()->where('module_key', 'crm')->sole();

    expect($row->updated_by_id)->toBe($this->actor->id)
        ->and($row->enabled_at)->not->toBeNull();
});

it('labels a requirement pulled in by another module', function () {
    $this->service->enable($this->company, 'sales', $this->actor);

    expect(CompanyModuleEvent::query()->where('module_key', 'inventory')->sole()->reason)
        ->toBe('Required by [sales].');
});

it('is idempotent when a module is already on', function () {
    $this->service->enable($this->company, 'crm', $this->actor);

    expect($this->service->enable($this->company, 'crm', $this->actor))->toBe([])
        ->and(CompanyModuleEvent::query()->where('module_key', 'crm')->count())->toBe(1);
});

it('refuses to disable an always-on module', function () {
    $this->service->disable($this->company, 'core', $this->actor);
})->throws(ValidationException::class, 'always on');

it('refuses to disable a module another enabled module depends on', function () {
    $this->service->enable($this->company, 'sales', $this->actor);

    expect(fn () => $this->service->disable($this->company, 'inventory', $this->actor))
        ->toThrow(ValidationException::class, 'required by');

    expect($this->service->isEnabled('inventory', $this->company->id))->toBeTrue();
});

it('disables dependents first when asked to cascade', function () {
    $this->service->enable($this->company, 'sales', $this->actor);

    $disabled = $this->service->disable($this->company, 'inventory', $this->actor, cascade: true);

    expect($disabled)->toContain('sales')->toContain('inventory')
        ->and($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'accounting']);

    expect(CompanyModuleEvent::query()->where('module_key', 'sales')->where('action', ModuleEventActionEnum::Disabled)->sole()->reason)
        ->toBe('Depends on [inventory].');
});

it('keeps the row and its settings when a module is disabled', function () {
    $this->service->enable($this->company, 'crm', $this->actor);
    $this->service->updateSettings($this->company, 'crm', ['reminder_hour' => 9], $this->actor);

    $this->service->disable($this->company, 'crm', $this->actor, 'Not needed this season.');

    $row = CompanyModule::query()->where('module_key', 'crm')->sole();

    expect($row->exists)->toBeTrue()
        ->and($row->is_enabled)->toBeFalse()
        ->and($row->disabled_at)->not->toBeNull()
        ->and($row->settings)->toBe(['reminder_hour' => 9]);
});

it('restores the previous state when a module is re-enabled', function () {
    $this->service->enable($this->company, 'crm', $this->actor);
    $this->service->updateSettings($this->company, 'crm', ['reminder_hour' => 9], $this->actor);
    $this->service->disable($this->company, 'crm', $this->actor);
    $this->service->enable($this->company, 'crm', $this->actor);

    expect($this->service->isEnabled('crm', $this->company->id))->toBeTrue()
        ->and($this->service->settingsFor($this->company->id, 'crm'))->toBe(['reminder_hour' => 9])
        ->and(CompanyModule::query()->where('module_key', 'crm')->count())->toBe(1);
});

it('rejects an unknown module key', function () {
    $this->service->enable($this->company, 'not-a-module', $this->actor);
})->throws(ValidationException::class, 'Unknown module');

it('applies category defaults without touching anything else', function () {
    $category = CompanyCategory::factory()->withModules(['crm'])->create();
    $this->company->update(['company_category_id' => $category->id]);
    $this->service->enable($this->company, 'hr', $this->actor);

    $changed = $this->service->syncFromCategory($this->company->fresh(), actor: $this->actor);

    expect($changed)->toContain('crm')
        ->and($this->service->enabledKeys($this->company->id))
        ->toContain('crm')
        ->toContain('hr');
});

it('only drops non-category modules when explicitly asked to', function () {
    $category = CompanyCategory::factory()->withModules(['crm'])->create();
    $this->company->update(['company_category_id' => $category->id]);
    $this->service->enable($this->company, 'hr', $this->actor);

    $this->service->syncFromCategory($this->company->fresh(), disableOthers: true, actor: $this->actor);

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'crm'])
        ->and(CompanyModuleEvent::query()->where('module_key', 'hr')->where('action', ModuleEventActionEnum::CategoryApplied)->exists())
        ->toBeTrue();
});

it('reconciles a downgrade by hiding modules rather than deleting them', function () {
    $this->service->enable($this->company, 'hr', $this->actor, source: ModuleSourceEnum::Category);

    $plan = Plan::factory()->create(['modules' => ['core', 'accounting']]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    $revoked = $this->service->reconcileWithPlan($this->company, $this->actor);

    expect($revoked)->toContain('hr')
        ->and(CompanyModule::query()->where('module_key', 'hr')->sole()->is_enabled)->toBeFalse()
        ->and(CompanyModuleEvent::query()->where('action', ModuleEventActionEnum::PlanReconciled)->exists())->toBeTrue();
});

it('leaves a manual override alone when reconciling with a plan', function () {
    $this->service->enable($this->company, 'hr', $this->actor, source: ModuleSourceEnum::Manual);

    $plan = Plan::factory()->create(['modules' => ['core']]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    expect($this->service->reconcileWithPlan($this->company, $this->actor))->toBe([])
        ->and($this->service->isEnabled('hr', $this->company->id))->toBeTrue();
});

it('writes the cap down for a category default that has no row yet', function () {
    $category = CompanyCategory::factory()->withModules(['hr'])->create();
    $this->company->update(['company_category_id' => $category->id]);

    $plan = Plan::factory()->create(['modules' => ['core']]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    $revoked = $this->service->reconcileWithPlan($this->company->fresh(), $this->actor);

    expect($revoked)->toContain('hr')
        ->and(CompanyModule::query()->where('module_key', 'hr')->sole()->is_enabled)->toBeFalse();
});

it('reconciles nothing for an uncapped plan', function () {
    $this->service->enable($this->company, 'hr', $this->actor);

    $plan = Plan::factory()->create(['modules' => null]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    expect($this->service->reconcileWithPlan($this->company, $this->actor))->toBe([]);
});

it('merges module settings over the registry defaults', function () {
    config()->set('modules.crm.settings_schema', ['reminder_hour' => 8, 'digest' => true]);
    app(ModuleRegistry::class)->flush();

    $merged = $this->service->updateSettings($this->company, 'crm', ['reminder_hour' => 18], $this->actor);

    expect($merged)->toBe(['reminder_hour' => 18, 'digest' => true])
        ->and($this->service->settingsFor($this->company->id, 'crm'))->toBe(['reminder_hour' => 18, 'digest' => true]);
});

it('materializes the resolved state into explicit rows', function () {
    $category = CompanyCategory::factory()->withModules(['crm'])->create();
    $this->company->update(['company_category_id' => $category->id]);

    $this->service->materializeFor($this->company->fresh(), $this->actor);

    $rows = CompanyModule::query()->where('company_id', $this->company->id)->get();

    expect($rows)->toHaveCount(count(app(ModuleRegistry::class)->keys()))
        ->and($rows->firstWhere('module_key', 'core')->source)->toBe(ModuleSourceEnum::Core)
        ->and($rows->firstWhere('module_key', 'crm')->is_enabled)->toBeTrue()
        ->and($rows->firstWhere('module_key', 'hr')->is_enabled)->toBeFalse();
});

it('computes the requirement closure of a set of keys', function () {
    expect($this->service->closure(['sales', 'unknown']))
        ->toEqualCanonicalizing(['sales', 'accounting', 'inventory']);
});

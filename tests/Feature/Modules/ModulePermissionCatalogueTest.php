<?php

use App\Models\Role;
use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use App\Services\PermissionRegistry;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 8 — the permission catalogue guarantee
| (saas-modular-platform plan §10.1; capping plan gap F5).
|
| The rule this pins down is subtle and easy to break: a disabled module's
| permissions disappear from what the role editor OFFERS, but stay exactly where
| they are on roles that already hold them. That is what makes re-enabling a
| module restore its behaviour rather than requiring every role to be rebuilt.
*/

beforeEach(function () {
    $this->service = app(CompanyModuleService::class);
    $this->registry = app(PermissionRegistry::class);

    $this->company = makeCompany('Acme', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales', 'crm'])->create()->id,
    ]);
    $this->company->refresh();

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Owner',
        'email' => 'owner@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, [], 'admin');
});

it('offers the permissions of the modules a company runs', function () {
    $permissions = $this->registry->forCompany((int) $this->company->id);

    expect($permissions)->toContain('list_invoice', 'list_crm_lead', 'list_product');
});

it('hides the permissions of a module the company does not run', function () {
    $permissions = $this->registry->forCompany((int) $this->company->id);

    expect($permissions)->not->toContain('list_member', 'list_employee', 'list_payroll');
});

it('keeps a permission a module no longer claims visible', function () {
    // A permission no module owns is a manifest gap, caught by
    // ModuleRegistryTest — it must not silently vanish from the role editor
    // while somebody fixes the registry.
    $orphan = 'a_permission_no_module_owns';

    config()->set('modules.crm.permissions', array_values(array_diff(
        config('modules.crm.permissions'),
        ['list_crm_lead'],
    )));
    app(App\Services\Modules\ModuleRegistry::class)->flush();

    expect(app(App\Services\Modules\ModuleRegistry::class)->moduleForPermission('list_crm_lead'))->toBeNull()
        ->and($this->registry->forCompany((int) $this->company->id))->toContain('list_crm_lead')
        ->and($orphan)->toBeString();
});

it('leaves the stored permissions of a role untouched when its module goes off', function () {
    $role = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Sales Rep',
        'permissions' => ['list_invoice', 'create_invoice', 'list_crm_lead'],
    ]);

    $this->service->disable($this->company, 'crm');

    expect($role->fresh()->permissions)->toBe(['list_invoice', 'create_invoice', 'list_crm_lead'])
        ->and($this->registry->forCompany((int) $this->company->id))->not->toContain('list_crm_lead');
});

it('restores the role\'s behaviour exactly when the module comes back', function () {
    $role = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Sales Rep',
        'permissions' => ['list_invoice', 'list_crm_lead'],
    ]);

    $this->service->disable($this->company, 'crm');
    $this->service->enable($this->company->fresh(), 'crm');

    expect($role->fresh()->permissions)->toBe(['list_invoice', 'list_crm_lead'])
        ->and($this->registry->forCompany((int) $this->company->id))->toContain('list_crm_lead');
});

it('drops a disabled module\'s permissions from the catalogue endpoint', function () {
    $before = collect($this->getJson('/api/admin/permission')->assertSuccessful()->json())->flatten()->toJson();

    expect($before)->toContain('crm');

    $this->service->disable($this->company, 'crm');

    $after = collect($this->getJson('/api/admin/permission')->assertSuccessful()->json())->flatten()->toJson();

    expect($after)->not->toContain('list_crm_lead');
});

it('validates against every enforced permission, not just the enabled ones', function () {
    // Validation deliberately uses all(), not forCompany(): a Super Admin
    // restoring a role, or a seeder, must be able to name a permission whose
    // module happens to be off right now.
    expect($this->registry->all())->toContain('list_member')
        ->and($this->registry->forCompany((int) $this->company->id))->not->toContain('list_member');
});

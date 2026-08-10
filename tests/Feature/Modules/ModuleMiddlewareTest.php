<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 2 — enforcement at the HTTP boundary
| (docs/saas-modular-platform-and-gym-module-plan.md §3.7).
|
| Modules are a packaging boundary, not a security one: CheckRoleMiddleware
| still owns permissions. The module middleware exists so a company that does
| not run a module gets a clear, machine-readable answer instead of a confusing
| permission error.
*/

beforeEach(function () {
    $this->company = makeCompany('Acme Fitness', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales'])->create()->id,
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

    $this->service = app(CompanyModuleService::class);
});

it('lets a request through when the module is enabled', function () {
    $this->getJson('/api/admin/product')->assertSuccessful();
});

it('blocks a request when the module is not enabled', function () {
    $response = $this->getJson('/api/admin/crm/task');

    $response->assertForbidden()
        ->assertJsonPath('code', 'module_disabled')
        ->assertJsonPath('module', 'crm');

    expect($response->json('message'))->toContain('CRM');
});

it('answers with the module error rather than a permission error', function () {
    // The user is a company admin and holds every permission, so a plain 403
    // would be actively misleading about what is wrong.
    $this->getJson('/api/admin/crm/task')->assertJsonPath('code', 'module_disabled');
});

it('starts blocking as soon as the module is switched off', function () {
    $this->getJson('/api/admin/product')->assertSuccessful();

    $this->service->disable($this->company, 'inventory', cascade: true);

    $this->getJson('/api/admin/product')
        ->assertForbidden()
        ->assertJsonPath('module', 'inventory');
});

it('starts allowing as soon as the module is switched on', function () {
    $this->getJson('/api/admin/crm/task')->assertForbidden();

    $this->service->enable($this->company, 'crm');

    $this->getJson('/api/admin/crm/task')->assertSuccessful();
});

it('never blocks a core route', function () {
    $this->getJson('/api/admin/party')->assertSuccessful();
    $this->getJson('/api/admin/profile')->assertSuccessful();
});

it('gates a sub-module living inside another module\'s route file', function () {
    // `manufacturing` routes sit inside api_inventory.php and are wrapped in an
    // inline module group, so inventory can be on while manufacturing is off.
    expect($this->service->isEnabled('inventory', $this->company->id))->toBeTrue()
        ->and($this->service->isEnabled('manufacturing', $this->company->id))->toBeFalse();

    $this->getJson('/api/admin/bom')
        ->assertForbidden()
        ->assertJsonPath('module', 'manufacturing');

    $this->service->enable($this->company, 'manufacturing');

    $this->getJson('/api/admin/bom')->assertSuccessful();
});

it('gates the payroll sub-module independently of hr', function () {
    $this->service->enable($this->company, 'hr');

    $this->getJson('/api/admin/hr/employee')->assertSuccessful();
    $this->getJson('/api/admin/hr/payroll')
        ->assertForbidden()
        ->assertJsonPath('module', 'payroll');
});

it('blocks a whole sub-module when its parent is off', function () {
    $this->service->enable($this->company, 'manufacturing');
    $this->service->disable($this->company, 'inventory', cascade: true);

    // inventory is the requirement, so manufacturing resolves off with it.
    $this->getJson('/api/admin/bom')->assertForbidden();
});

it('gives an unconfigured company the pre-modular ERP', function () {
    $legacy = makeCompany('Legacy Co', 'LEG');
    $user = User::create([
        'company_id' => $legacy->id,
        'name' => 'Legacy Owner',
        'email' => 'legacy@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($user, [], 'admin');

    $this->getJson('/api/admin/product')->assertSuccessful();
    $this->getJson('/api/admin/crm/task')->assertSuccessful();
});

it('hides a disabled module\'s permissions from the role editor', function () {
    $catalogue = $this->getJson('/api/admin/permission')->assertSuccessful()->json('data');

    $flat = collect($catalogue)->flatMap(fn (array $groups): array => collect($groups)->flatten(1)->all())
        ->pluck('permission');

    expect($flat)->toContain('list_product')
        ->and($flat)->not->toContain('list_crm_task');
});

it('shows them again once the module is back on', function () {
    $this->service->enable($this->company, 'crm');

    $catalogue = $this->getJson('/api/admin/permission')->assertSuccessful()->json('data');

    $flat = collect($catalogue)->flatMap(fn (array $groups): array => collect($groups)->flatten(1)->all())
        ->pluck('permission');

    expect($flat)->toContain('list_crm_task');
});

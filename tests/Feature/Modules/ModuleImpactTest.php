<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 6 — the disable preflight
| (docs/module-capping-and-advanced-handling-plan.md gap F1).
|
| Disabling a module is lossless, but it is not *nothing*: other modules cascade
| with it, background commands stop, and work can be mid-flight. The Super Admin
| gets that picture before clicking — and asking for it must change nothing.
*/

beforeEach(function () {
    $this->superAdmin = actingAsSuperAdmin();

    $this->company = makeCompany('Acme Fitness', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()
            ->withModules(['accounting', 'inventory', 'sales', 'purchase', 'gym'])
            ->create()->id,
    ]);
    $this->company->refresh();
});

it('reports what would cascade with the module', function () {
    $response = $this->getJson("/api/super-admin/company/{$this->company->id}/module/inventory/impact")
        ->assertSuccessful();

    expect($response->json('data.module'))->toBe('inventory')
        ->and($response->json('data.enabled'))->toBeTrue()
        ->and(array_column($response->json('data.cascade'), 'key'))
        ->toContain('sales', 'gym');
});

it('counts the rows the module owns without touching them', function () {
    $gym = collect(app(App\Services\Modules\ModuleRegistry::class)->get('gym')['models']);

    $response = $this->getJson("/api/super-admin/company/{$this->company->id}/module/gym/impact")
        ->assertSuccessful();

    expect($response->json('data.records'))->toHaveCount($gym->count())
        ->and($response->json('data.total_records'))->toBe(0)
        ->and(array_column($response->json('data.records'), 'model'))->toContain('Member', 'Membership');
});

it('states plainly that disabling is reversible', function () {
    $this->getJson("/api/super-admin/company/{$this->company->id}/module/gym/impact")
        ->assertSuccessful()
        ->assertJsonPath('data.reversible', true);
});

it('lists the scheduled commands that would stop', function () {
    $this->getJson("/api/super-admin/company/{$this->company->id}/module/gym/impact")
        ->assertSuccessful()
        ->assertJsonPath('data.scheduled_commands', [
            'gym:process-membership-expiry',
            'gym:dispatch-membership-reminders',
        ]);
});

it('changes nothing at all', function () {
    $before = app(CompanyModuleService::class)->enabledKeys((int) $this->company->id);
    $eventsBefore = App\Models\CompanyModuleEvent::withoutGlobalScopes()->count();

    $this->getJson("/api/super-admin/company/{$this->company->id}/module/inventory/impact")->assertSuccessful();

    expect(app(CompanyModuleService::class)->enabledKeys((int) $this->company->id))->toBe($before)
        ->and(App\Models\CompanyModuleEvent::withoutGlobalScopes()->count())->toBe($eventsBefore);
});

it('rejects an unknown module', function () {
    $this->getJson("/api/super-admin/company/{$this->company->id}/module/not-a-module/impact")
        ->assertStatus(422)
        ->assertJsonValidationErrors('module_key');
});

it('lets a tenant read its own module history', function () {
    app(CompanyModuleService::class)->disable($this->company, 'gym', reason: 'Not renewing.');

    $user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Owner',
        'email' => 'owner@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($user, [], 'admin');

    $response = $this->getJson('/api/admin/module/event')->assertSuccessful();

    $gymEvent = collect($response->json('data'))->firstWhere('module_key', 'gym');

    expect($gymEvent['module_name'])->toBe('Gym Management')
        ->and($gymEvent['reason'])->toBe('Not renewing.')
        // Deliberately thinner than the Super Admin trail: no platform actor.
        ->and($gymEvent)->not->toHaveKey('actor');
});

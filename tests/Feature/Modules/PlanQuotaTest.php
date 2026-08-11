<?php

use App\Models\Plan;
use App\Models\User;
use App\Models\Branch;
use App\Enums\UserTypeEnum;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use App\Services\Billing\QuotaService;

/*
| Phase 7 — plan quotas
| (docs/module-capping-and-advanced-handling-plan.md Part 2, gaps F3/F4).
|
| The second axis of capping: modules decide whether a feature exists for a
| company, quotas decide how much of it. Two rules the tests pin down —
| a null limit is unlimited, and going over a limit refuses the NEXT creation
| without touching anything that already exists.
*/

beforeEach(function () {
    $this->company = makeCompany('Acme', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory'])->create()->id,
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

    $this->quotas = app(QuotaService::class);
});

function subscribeCompanyTo(App\Models\Company $company, array $attributes): Plan
{
    $plan = Plan::factory()->create($attributes);

    Subscription::factory()->create(['company_id' => $company->id, 'plan_id' => $plan->id]);

    return $plan;
}

it('treats a plan with no limit as unlimited', function () {
    subscribeCompanyTo($this->company, ['branch_limit' => null, 'limits' => null]);

    $state = $this->quotas->check('branches', $this->company->fresh());

    expect($state['unlimited'])->toBeTrue()
        ->and($state['limit'])->toBeNull()
        ->and($state['exceeded'])->toBeFalse()
        ->and($state['remaining'])->toBeNull();
});

it('treats a company with no plan at all as unlimited', function () {
    expect($this->quotas->allows('branches', $this->company))->toBeTrue();
});

it('counts current usage against the limit', function () {
    subscribeCompanyTo($this->company, ['branch_limit' => 2]);

    Branch::create(['company_id' => $this->company->id, 'name' => 'HO', 'code' => 'HO', 'is_head_office' => true]);

    $state = $this->quotas->check('branches', $this->company->fresh());

    expect($state['used'])->toBe(1)
        ->and($state['limit'])->toBe(2)
        ->and($state['remaining'])->toBe(1)
        ->and($state['exceeded'])->toBeFalse();
});

it('refuses the creation that would exceed the limit', function () {
    subscribeCompanyTo($this->company, ['branch_limit' => 1, 'name' => 'Basic']);

    Branch::create(['company_id' => $this->company->id, 'name' => 'HO', 'code' => 'HO', 'is_head_office' => true]);

    $response = $this->postJson('/api/admin/branch', ['name' => 'Second', 'code' => 'BR2'])
        ->assertStatus(422);

    $response->assertJsonPath('code', 'quota_exceeded')
        ->assertJsonPath('limit_key', 'branches')
        ->assertJsonPath('limit', 1)
        ->assertJsonPath('used', 1);

    expect($response->json('message'))->toContain('Basic');
});

it('allows the creation while there is headroom', function () {
    subscribeCompanyTo($this->company, ['branch_limit' => 2]);

    Branch::create(['company_id' => $this->company->id, 'name' => 'HO', 'code' => 'HO', 'is_head_office' => true]);

    $this->postJson('/api/admin/branch', ['name' => 'Second', 'code' => 'BR2'])->assertSuccessful();
});

it('caps users through the shared registry too', function () {
    subscribeCompanyTo($this->company, ['limits' => ['users' => 1]]);

    $role = App\Models\Role::create([
        'company_id' => $this->company->id,
        'name' => 'Staff',
        'permissions' => ['list_user'],
    ]);

    // The owner created in beforeEach is already the one allowed user.
    $this->postJson('/api/admin/user', [
        'name' => 'Second',
        'email' => 'second@acme.test',
        'roles' => [$role->id],
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'quota_exceeded')
        ->assertJsonPath('limit_key', 'users');
});

it('lets a user through while the plan still has room', function () {
    subscribeCompanyTo($this->company, ['limits' => ['users' => 5]]);

    $role = App\Models\Role::create([
        'company_id' => $this->company->id,
        'name' => 'Staff',
        'permissions' => ['list_user'],
    ]);

    $this->postJson('/api/admin/user', [
        'name' => 'Second',
        'email' => 'second@acme.test',
        'roles' => [$role->id],
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSuccessful();
});

it('never hides or deletes anything when a downgrade puts a company over a limit', function () {
    subscribeCompanyTo($this->company, ['branch_limit' => 3]);

    foreach (['HO', 'BR2', 'BR3'] as $code) {
        Branch::create(['company_id' => $this->company->id, 'name' => $code, 'code' => $code]);
    }

    // Downgrade below current usage.
    $this->company->fresh()->plan()->first()->update(['branch_limit' => 1]);

    $state = $this->quotas->check('branches', $this->company->fresh());

    expect($state['exceeded'])->toBeTrue()
        ->and($state['used'])->toBe(3)
        ->and($state['remaining'])->toBe(0)
        // Every branch is still there and still listed.
        ->and(Branch::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(3);

    $this->getJson('/api/admin/branch')->assertSuccessful()->assertJsonCount(3, 'data');
});

it('leaves out the quotas of modules the company does not run', function () {
    subscribeCompanyTo($this->company, ['limits' => ['warehouses' => 5, 'gym_members' => 100]]);

    $keys = array_column($this->quotas->all($this->company->fresh()), 'key');

    expect($keys)
        ->toContain('branches', 'users', 'warehouses')
        ->not->toContain('gym_members');
});

it('reports headroom for the usage screen', function () {
    subscribeCompanyTo($this->company, ['branch_limit' => 4]);

    Branch::create(['company_id' => $this->company->id, 'name' => 'HO', 'code' => 'HO']);

    $branches = collect($this->getJson('/api/admin/billing/usage')->assertSuccessful()->json('data'))
        ->firstWhere('key', 'branches');

    expect($branches['used'])->toBe(1)
        ->and($branches['limit'])->toBe(4)
        ->and($branches['remaining'])->toBe(3);
});

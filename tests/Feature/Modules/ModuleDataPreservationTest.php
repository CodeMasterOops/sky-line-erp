<?php

use App\Models\Tag;
use App\Models\Note;
use App\Models\User;
use App\Models\Party;
use App\Models\Branch;
use App\Models\FollowUp;
use App\Enums\UserTypeEnum;
use App\Models\CrmActivity;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyModule;
use App\Models\CompanyCategory;
use App\Services\TenantService;
use App\Services\Modules\CompanyModuleService;

/*
| The headline guarantee of the whole plan: a module can be switched off and
| back on without touching a single row of tenant data
| (docs/saas-modular-platform-and-gym-module-plan.md §3.1, §10.1).
|
| CRM stands in for every module here — it owns several tables, including
| polymorphic ones shared with the rest of the ERP, so it exercises the awkward
| cases rather than a toy one.
*/

beforeEach(function () {
    $this->company = makeCompany('Acme Fitness', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales', 'crm'])->create()->id,
    ]);
    $this->company->refresh();

    $this->branch = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Head Office',
        'code' => 'HO',
        'is_head_office' => true,
    ]);

    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId($this->branch->id);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Owner',
        'email' => 'owner@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->service = app(CompanyModuleService::class);
});

function seedCrmData(): array
{
    $party = Party::create([
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Ram Bahadur',
        'code' => 'CUST-0001',
        'phone' => '9800000000',
    ]);

    $followUp = FollowUp::create([
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'party_id' => $party->id,
        'user_id' => test()->user->id,
        'scheduled_at' => now()->addDay(),
    ]);

    $note = Note::create([
        'company_id' => test()->company->id,
        'branch_id' => test()->branch->id,
        'notable_type' => Party::class,
        'notable_id' => $party->id,
        'body' => 'Prefers evening calls.',
        'user_id' => test()->user->id,
    ]);

    $tag = Tag::create(['company_id' => test()->company->id, 'name' => 'VIP', 'color' => '#ff0000']);
    $party->tags()->attach($tag->id);

    return compact('party', 'followUp', 'note', 'tag');
}

function crmRowCounts(int $companyId): array
{
    return [
        'parties' => Party::withoutGlobalScopes()->where('company_id', $companyId)->count(),
        'follow_ups' => FollowUp::withoutGlobalScopes()->where('company_id', $companyId)->count(),
        'notes' => Note::withoutGlobalScopes()->where('company_id', $companyId)->count(),
        'tags' => Tag::withoutGlobalScopes()->where('company_id', $companyId)->count(),
        'activities' => CrmActivity::withoutGlobalScopes()->where('company_id', $companyId)->count(),
    ];
}

it('does not delete a single row when a module is disabled', function () {
    seedCrmData();
    $before = crmRowCounts($this->company->id);

    $this->service->disable($this->company, 'crm');

    expect(crmRowCounts($this->company->id))->toBe($before);
});

it('does not soft-delete anything either', function () {
    ['party' => $party, 'followUp' => $followUp] = seedCrmData();

    $this->service->disable($this->company, 'crm');

    expect(Party::withoutGlobalScopes()->find($party->id)->deleted_at)->toBeNull()
        ->and(FollowUp::withoutGlobalScopes()->find($followUp->id))->not->toBeNull();
});

it('restores the module intact when it is switched back on', function () {
    ['party' => $party, 'note' => $note, 'tag' => $tag] = seedCrmData();
    $before = crmRowCounts($this->company->id);

    $this->service->disable($this->company, 'crm');
    $this->service->enable($this->company, 'crm');

    expect(crmRowCounts($this->company->id))->toBe($before)
        ->and($this->service->isEnabled('crm', $this->company->id))->toBeTrue();

    $reloaded = Party::withoutGlobalScopes()->with(['notes', 'tags'])->find($party->id);

    expect($reloaded->name)->toBe('Ram Bahadur')
        ->and($reloaded->notes->pluck('id'))->toContain($note->id)
        ->and($reloaded->tags->pluck('id'))->toContain($tag->id);
});

it('keeps the data reachable from modules that are still on', function () {
    // A party is a core record that CRM decorates. Turning CRM off must not
    // take the customer away from Sales.
    ['party' => $party] = seedCrmData();

    $this->service->disable($this->company, 'crm');

    Sanctum::actingAs($this->user, [], 'admin');

    $this->getJson('/api/admin/party/'.$party->id)->assertSuccessful();
});

it('survives several off/on cycles without drift', function () {
    seedCrmData();
    $before = crmRowCounts($this->company->id);

    foreach (range(1, 3) as $ignored) {
        $this->service->disable($this->company, 'crm');
        $this->service->enable($this->company, 'crm');
    }

    expect(crmRowCounts($this->company->id))->toBe($before)
        ->and(CompanyModule::query()->where('company_id', $this->company->id)->where('module_key', 'crm')->count())->toBe(1);
});

it('leaves a role\'s stored permissions alone while its module is off', function () {
    $role = App\Models\Role::create([
        'company_id' => $this->company->id,
        'name' => 'CRM Executive',
        'permissions' => ['list_crm_task', 'list_party'],
    ]);

    $this->service->disable($this->company, 'crm');

    expect($role->fresh()->permissions)->toBe(['list_crm_task', 'list_party']);

    $this->service->enable($this->company, 'crm');

    expect($role->fresh()->permissions)->toBe(['list_crm_task', 'list_party']);
});

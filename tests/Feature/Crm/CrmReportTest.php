<?php

use App\Models\Task;
use App\Models\User;
use App\Models\Party;
use App\Models\FollowUp;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\TaskStatusEnum;
use App\Models\CrmLeadProfile;
use App\Services\TenantService;
use App\Enums\CrmLeadStatusEnum;
use App\Enums\FollowUpStatusEnum;

beforeEach(function () {
    warmAllTablesCache();

    $this->company = makeCompany('Acme CRM', 'ACME');
    TenantService::setCompanyId($this->company->id);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Sales Admin',
        'email' => 'admin@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    Sanctum::actingAs($this->user, ['*'], 'admin');
});

function makeLeadWithStatus(int $companyId, CrmLeadStatusEnum $status, array $profile = []): Party
{
    $party = Party::create([
        'company_id' => $companyId,
        'type' => PartyTypeEnum::LEAD,
        'name' => 'Lead '.fake()->unique()->numerify('###'),
        'code' => 'LEAD-'.fake()->unique()->numerify('####'),
    ]);

    $party->leadProfile()->update(array_merge(['status' => $status->value], $profile));

    return $party;
}

it('reports the lead pipeline grouped by status', function () {
    makeLeadWithStatus($this->company->id, CrmLeadStatusEnum::New);
    makeLeadWithStatus($this->company->id, CrmLeadStatusEnum::New);
    makeLeadWithStatus($this->company->id, CrmLeadStatusEnum::Qualified);

    $response = $this->getJson(route('api.admin.crm.report.pipeline'));

    $response->assertOk()
        ->assertJsonPath('data.total', 3)
        ->assertJsonCount(5, 'data.by_status');

    $byStatus = collect($response->json('data.by_status'))->keyBy('status');
    expect($byStatus['new']['count'])->toBe(2)
        ->and($byStatus['qualified']['count'])->toBe(1)
        ->and($byStatus['lost']['count'])->toBe(0);
});

it('reports the conversion rate and average days to convert', function () {
    makeLeadWithStatus($this->company->id, CrmLeadStatusEnum::New);
    makeLeadWithStatus($this->company->id, CrmLeadStatusEnum::Qualified);

    $converted = makeLeadWithStatus($this->company->id, CrmLeadStatusEnum::Converted);
    $converted->leadProfile->forceFill([
        'created_at' => now()->subDays(10),
        'converted_at' => now(),
    ])->save();

    $response = $this->getJson(route('api.admin.crm.report.conversion'));

    $response->assertOk()
        ->assertJsonPath('data.total_leads', 3)
        ->assertJsonPath('data.converted', 1)
        ->assertJsonPath('data.avg_days_to_convert', 10);

    expect($response->json('data.conversion_rate'))->toEqualWithDelta(33.3, 0.1);
});

it('reports follow-ups by status with due and overdue counts', function () {
    $party = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Cust',
        'code' => 'C-1',
    ]);

    FollowUp::factory()->for($party)->create(['user_id' => $this->user->id, 'scheduled_at' => now()->subDays(2)]); // overdue + due
    FollowUp::factory()->for($party)->create(['user_id' => $this->user->id, 'scheduled_at' => now()->addWeek()]); // pending, not due
    FollowUp::factory()->for($party)->done()->create(['user_id' => $this->user->id, 'scheduled_at' => now()->subDay()]);

    $response = $this->getJson(route('api.admin.crm.report.follow-ups'));

    $response->assertOk()
        ->assertJsonPath('data.due', 1)
        ->assertJsonPath('data.overdue', 1);

    $byStatus = collect($response->json('data.by_status'))->keyBy('status');
    expect($byStatus[FollowUpStatusEnum::Pending->value]['count'])->toBe(2)
        ->and($byStatus[FollowUpStatusEnum::Done->value]['count'])->toBe(1);
});

it('reports tasks with overdue count and breakdown by assignee', function () {
    $rep = User::create([
        'company_id' => $this->company->id,
        'name' => 'Rep',
        'email' => 'rep@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Task::factory()->create(['assigned_to_user_id' => $this->user->id, 'status' => TaskStatusEnum::Open->value, 'due_date' => now()->subDays(3)->toDateString()]);
    Task::factory()->create(['assigned_to_user_id' => $rep->id, 'status' => TaskStatusEnum::Open->value, 'due_date' => now()->addDays(3)->toDateString()]);
    Task::factory()->done()->create(['assigned_to_user_id' => $rep->id, 'due_date' => now()->subDays(5)->toDateString()]);

    $response = $this->getJson(route('api.admin.crm.report.tasks'));

    $response->assertOk()
        ->assertJsonPath('data.overdue', 1)
        ->assertJsonCount(2, 'data.by_assignee');

    $byStatus = collect($response->json('data.by_status'))->keyBy('status');
    expect($byStatus['open']['count'])->toBe(2)
        ->and($byStatus['done']['count'])->toBe(1);
});

it('filters the tasks report by assignee', function () {
    $rep = User::create([
        'company_id' => $this->company->id,
        'name' => 'Rep',
        'email' => 'rep@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Task::factory()->create(['assigned_to_user_id' => $this->user->id, 'status' => TaskStatusEnum::Open->value]);
    Task::factory()->create(['assigned_to_user_id' => $rep->id, 'status' => TaskStatusEnum::Open->value]);

    $response = $this->getJson(route('api.admin.crm.report.tasks', ['assigned_to_user_id' => $rep->id]));

    $response->assertOk()->assertJsonCount(1, 'data.by_assignee');
    expect(collect($response->json('data.by_status'))->keyBy('status')['open']['count'])->toBe(1);
});

it('scopes the pipeline report to the active company', function () {
    makeLeadWithStatus($this->company->id, CrmLeadStatusEnum::New);

    // A user from another company must not see this company's leads.
    $other = makeCompany('Other Co', 'OTHR');
    TenantService::setCompanyId($other->id);
    $otherUser = User::create([
        'company_id' => $other->id,
        'name' => 'Other Admin',
        'email' => 'other@othr.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    Sanctum::actingAs($otherUser, ['*'], 'admin');

    expect(CrmLeadProfile::count())->toBe(0);

    $this->getJson(route('api.admin.crm.report.pipeline'))
        ->assertOk()->assertJsonPath('data.total', 0);
});

it('forbids the reports without permission', function () {
    $user = User::create([
        'company_id' => $this->company->id,
        'name' => 'No Access',
        'email' => 'noaccess@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);
    $user->roles()->create([
        'company_id' => $this->company->id,
        'name' => 'Empty',
        'permissions' => [],
    ]);
    Sanctum::actingAs($user, ['*'], 'admin');

    $this->getJson(route('api.admin.crm.report.pipeline'))->assertForbidden();
});

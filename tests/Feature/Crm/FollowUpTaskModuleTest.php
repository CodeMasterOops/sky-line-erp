<?php

use App\Models\Task;
use App\Models\User;
use App\Models\Party;
use App\Models\FollowUp;
use App\Enums\UserTypeEnum;
use App\Models\CrmActivity;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\TaskStatusEnum;
use App\Services\TenantService;
use App\Enums\CrmActivityTypeEnum;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CrmReminderNotification;

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

function makeParty(int $companyId, PartyTypeEnum $type = PartyTypeEnum::CUSTOMER): Party
{
    return Party::create([
        'company_id' => $companyId,
        'type' => $type,
        'name' => 'Party '.fake()->unique()->numerify('###'),
        'code' => 'P-'.fake()->unique()->numerify('####'),
    ]);
}

it('schedules a follow-up and logs an activity', function () {
    $party = makeParty($this->company->id);

    $response = $this->postJson(route('api.admin.follow-up.store'), [
        'party_id' => $party->id,
        'channel' => 'call',
        'scheduled_at' => now()->addDay()->toIso8601String(),
        'note' => 'Discuss renewal',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.channel', 'call')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.user_id', $this->user->id);

    expect(CrmActivity::where('subject_id', $party->id)
        ->where('type', CrmActivityTypeEnum::FollowUpScheduled->value)->count())->toBe(1);
});

it('completes a follow-up, stamps completion and logs an activity', function () {
    $party = makeParty($this->company->id);
    $followUp = FollowUp::factory()->for($party)->create(['user_id' => $this->user->id]);

    $this->postJson(route('api.admin.crm.follow-up.complete', $followUp), [
        'outcome' => 'Customer agreed to renew',
    ])->assertOk()->assertJsonPath('data.status', 'done');

    $followUp->refresh();
    expect($followUp->completed_at)->not->toBeNull()
        ->and($followUp->outcome)->toBe('Customer agreed to renew');

    expect(CrmActivity::where('subject_id', $party->id)
        ->where('type', CrmActivityTypeEnum::FollowUpCompleted->value)->count())->toBe(1);
});

it('lists only due (past, pending) follow-ups via the due endpoint', function () {
    $party = makeParty($this->company->id);
    FollowUp::factory()->for($party)->create(['user_id' => $this->user->id, 'scheduled_at' => now()->subHour()]);
    FollowUp::factory()->for($party)->create(['user_id' => $this->user->id, 'scheduled_at' => now()->addWeek()]);
    FollowUp::factory()->for($party)->done()->create(['user_id' => $this->user->id, 'scheduled_at' => now()->subDay()]);

    $this->getJson(route('api.admin.crm.follow-up.due'))
        ->assertOk()->assertJsonCount(1, 'data');
});

it('creates a task against a party and logs an activity', function () {
    $party = makeParty($this->company->id);

    $response = $this->postJson(route('api.admin.task.store'), [
        'title' => 'Send quotation',
        'party_id' => $party->id,
        'priority' => 'high',
        'assigned_to_user_id' => $this->user->id,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Send quotation')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.party_id', $party->id)
        ->assertJsonPath('data.created_by_user_id', $this->user->id);

    expect(CrmActivity::where('subject_id', $party->id)
        ->where('type', CrmActivityTypeEnum::TaskCreated->value)->count())->toBe(1);
});

it('creates a standalone task with no taskable', function () {
    $this->postJson(route('api.admin.task.store'), [
        'title' => 'Internal review',
    ])->assertCreated()->assertJsonPath('data.party_id', null);

    expect(Task::whereNull('taskable_id')->count())->toBe(1);
});

it('lists my open tasks only', function () {
    $other = User::create([
        'company_id' => $this->company->id,
        'name' => 'Other Rep',
        'email' => 'rep@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Task::factory()->create(['assigned_to_user_id' => $this->user->id]);
    Task::factory()->done()->create(['assigned_to_user_id' => $this->user->id]);
    Task::factory()->create(['assigned_to_user_id' => $other->id]);

    $this->getJson(route('api.admin.crm.task.mine'))
        ->assertOk()->assertJsonCount(1, 'data');
});

it('completes a task and logs an activity for its party', function () {
    $party = makeParty($this->company->id);
    $task = Task::factory()->for($party, 'taskable')->create(['assigned_to_user_id' => $this->user->id]);

    $this->postJson(route('api.admin.crm.task.complete', $task))
        ->assertOk()->assertJsonPath('data.status', 'done');

    expect($task->refresh()->completed_at)->not->toBeNull();

    expect(CrmActivity::where('subject_id', $party->id)
        ->where('type', CrmActivityTypeEnum::TaskCompleted->value)->count())->toBe(1);
});

it('filters overdue tasks', function () {
    Task::factory()->create(['due_date' => now()->subDays(2)->toDateString(), 'status' => TaskStatusEnum::Open->value]);
    Task::factory()->create(['due_date' => now()->addDays(2)->toDateString(), 'status' => TaskStatusEnum::Open->value]);
    Task::factory()->done()->create(['due_date' => now()->subDays(5)->toDateString()]);

    $this->getJson(route('api.admin.task.index', ['overdue' => 1]))
        ->assertOk()->assertJsonCount(1, 'data');
});

it('dispatches reminders once for due follow-ups and tasks', function () {
    Notification::fake();

    $party = makeParty($this->company->id);
    $followUp = FollowUp::factory()->for($party)->create([
        'user_id' => $this->user->id,
        'scheduled_at' => now()->subMinutes(5),
    ]);
    $task = Task::factory()->create([
        'assigned_to_user_id' => $this->user->id,
        'reminder_at' => now()->subMinutes(5),
        'status' => TaskStatusEnum::Open->value,
    ]);

    $this->artisan('crm:dispatch-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->user, CrmReminderNotification::class, 2);

    expect($followUp->refresh()->reminded_at)->not->toBeNull()
        ->and($task->refresh()->reminded_at)->not->toBeNull();

    // Running again must not re-notify already-reminded items.
    $this->artisan('crm:dispatch-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->user, CrmReminderNotification::class, 2);
});

it('does not remind for completed tasks or done follow-ups', function () {
    Notification::fake();

    $party = makeParty($this->company->id);
    FollowUp::factory()->for($party)->done()->create([
        'user_id' => $this->user->id,
        'scheduled_at' => now()->subMinutes(5),
    ]);
    Task::factory()->done()->create([
        'assigned_to_user_id' => $this->user->id,
        'reminder_at' => now()->subMinutes(5),
    ]);

    $this->artisan('crm:dispatch-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('scopes follow-ups and tasks to the active company', function () {
    $party = makeParty($this->company->id);
    FollowUp::factory()->for($party)->create(['user_id' => $this->user->id]);
    Task::factory()->create(['assigned_to_user_id' => $this->user->id]);

    $other = makeCompany('Other Co', 'OTHR');
    TenantService::setCompanyId($other->id);

    expect(FollowUp::count())->toBe(0)
        ->and(Task::count())->toBe(0);
});

it('forbids listing tasks without permission', function () {
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

    $this->getJson(route('api.admin.task.index'))->assertForbidden();
    $this->getJson(route('api.admin.follow-up.index'))->assertForbidden();
});

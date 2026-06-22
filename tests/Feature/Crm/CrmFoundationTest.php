<?php

use App\Models\Tag;
use App\Models\Role;
use App\Models\User;
use App\Models\Party;
use App\Enums\UserTypeEnum;
use App\Models\CrmActivity;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use App\Enums\CrmActivityTypeEnum;
use App\Services\Crm\ActivityLogger;

beforeEach(function () {
    warmAllTablesCache();

    $this->company = makeCompany('Acme CRM', 'ACME');
    TenantService::setCompanyId($this->company->id);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Globex Ltd',
        'code' => 'CUST-0001',
    ]);
});

it('attaches notes, tags and activities to a party polymorphically', function () {
    $note = $this->party->notes()->create(['body' => 'Called the customer.']);

    $tag = Tag::create(['name' => 'VIP Client']);
    $this->party->tags()->attach($tag);

    $activity = $this->party->activities()->create([
        'type' => CrmActivityTypeEnum::NoteAdded->value,
        'description' => 'Note added',
        'occurred_at' => now(),
    ]);

    expect($this->party->notes()->count())->toBe(1)
        ->and($note->notable_type)->toBe(Party::class)
        ->and($note->notable_id)->toBe($this->party->id)
        ->and($this->party->tags()->count())->toBe(1)
        ->and($this->party->activities()->count())->toBe(1)
        ->and($activity->subject_type)->toBe(Party::class);
});

it('auto-generates a tag slug from its name', function () {
    $tag = Tag::create(['name' => 'High Priority']);

    expect($tag->slug)->toBe('high-priority')
        ->and($tag->company_id)->toBe($this->company->id);
});

it('records a crm activity via the ActivityLogger with tenant + causer context', function () {
    $user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Sales Rep',
        'email' => 'rep@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    Sanctum::actingAs($user, ['*'], 'admin');

    $activity = app(ActivityLogger::class)->log(
        $this->party,
        CrmActivityTypeEnum::LeadCreated,
        'Lead created',
        ['source' => 'walk_in'],
    );

    expect($activity->type)->toBe('lead_created')
        ->and($activity->description)->toBe('Lead created')
        ->and($activity->properties)->toBe(['source' => 'walk_in'])
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->company_id)->toBe($this->company->id)
        ->and($activity->subject_id)->toBe($this->party->id);
});

it('scopes crm activities to the active company', function () {
    $this->party->activities()->create([
        'type' => CrmActivityTypeEnum::LeadCreated->value,
        'description' => 'For Acme',
        'occurred_at' => now(),
    ]);

    expect(CrmActivity::count())->toBe(1);

    $otherCompany = makeCompany('Other Co', 'OTHR');
    TenantService::setCompanyId($otherCompany->id);

    expect(CrmActivity::count())->toBe(0);
});

it('returns the party timeline through the api for an authorized admin', function () {
    $user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin',
        'email' => 'admin@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    Sanctum::actingAs($user, ['*'], 'admin');

    $this->party->activities()->createMany([
        ['type' => CrmActivityTypeEnum::LeadCreated->value, 'description' => 'one', 'occurred_at' => now()->subDay()],
        ['type' => CrmActivityTypeEnum::NoteAdded->value, 'description' => 'two', 'occurred_at' => now()],
    ]);

    $response = $this->getJson(route('api.admin.crm.customer.timeline', $this->party));

    $response->assertOk()->assertJsonCount(2, 'data');
});

it('forbids the timeline for a user without the permission', function () {
    $user = User::create([
        'company_id' => $this->company->id,
        'name' => 'No Access',
        'email' => 'noaccess@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    $role = Role::create([
        'company_id' => $this->company->id,
        'name' => 'Viewer',
        'permissions' => ['list_party'],
    ]);
    $user->roles()->attach($role);

    Sanctum::actingAs($user, ['*'], 'admin');

    $this->getJson(route('api.admin.crm.customer.timeline', $this->party))
        ->assertForbidden();
});

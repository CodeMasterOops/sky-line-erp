<?php

use App\Models\Tag;
use App\Models\User;
use App\Models\Party;
use App\Enums\UserTypeEnum;
use App\Models\CrmActivity;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\ContactPerson;
use App\Models\CrmLeadProfile;
use App\Services\TenantService;
use App\Enums\CrmLeadStatusEnum;
use App\Enums\CrmActivityTypeEnum;

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

function makeLead(int $companyId, array $profile = []): Party
{
    $party = Party::create([
        'company_id' => $companyId,
        'type' => PartyTypeEnum::LEAD,
        'name' => 'Prospect '.fake()->unique()->numerify('###'),
        'code' => 'LEAD-'.fake()->unique()->numerify('####'),
    ]);

    $party->leadProfile()->create(array_merge([
        'status' => CrmLeadStatusEnum::New->value,
        'source' => 'website',
    ], $profile));

    return $party;
}

it('creates a lead with a profile and logs an activity', function () {
    $response = $this->postJson(route('api.admin.lead.store'), [
        'name' => 'Globex Ltd',
        'source' => 'referral',
        'expected_value' => 50000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'lead')
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.source', 'referral');

    $party = Party::where('name', 'Globex Ltd')->first();

    expect($party->type)->toBe(PartyTypeEnum::LEAD)
        ->and($party->code)->toStartWith('LEAD-')
        ->and($party->leadProfile)->not->toBeNull()
        ->and($party->leadProfile->created_by_user_id)->toBe($this->user->id);

    expect(CrmActivity::where('subject_id', $party->id)
        ->where('type', CrmActivityTypeEnum::LeadCreated->value)->count())->toBe(1);
});

it('lists only leads and not customers', function () {
    makeLead($this->company->id);

    Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Existing Customer',
        'code' => 'CUST-0001',
    ]);

    $response = $this->getJson(route('api.admin.lead.index'));

    $response->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'lead');
});

it('updates a lead status to qualified and stamps qualified_at', function () {
    $lead = makeLead($this->company->id);

    $response = $this->patchJson(route('api.admin.crm.lead.status', $lead), [
        'status' => CrmLeadStatusEnum::Qualified->value,
    ]);

    $response->assertOk()->assertJsonPath('data.status', 'qualified');

    expect($lead->leadProfile->refresh()->qualified_at)->not->toBeNull();

    expect(CrmActivity::where('subject_id', $lead->id)
        ->where('type', CrmActivityTypeEnum::StatusChanged->value)->count())->toBe(1);
});

it('rejects setting converted through the status endpoint', function () {
    $lead = makeLead($this->company->id);

    $this->patchJson(route('api.admin.crm.lead.status', $lead), [
        'status' => CrmLeadStatusEnum::Converted->value,
    ])->assertStatus(422);
});

it('requires a lost reason when marking a lead lost', function () {
    $lead = makeLead($this->company->id);

    $this->patchJson(route('api.admin.crm.lead.status', $lead), [
        'status' => CrmLeadStatusEnum::Lost->value,
    ])->assertStatus(422);

    $this->patchJson(route('api.admin.crm.lead.status', $lead), [
        'status' => CrmLeadStatusEnum::Lost->value,
        'lost_reason' => 'Chose a competitor',
    ])->assertOk();

    expect($lead->leadProfile->refresh()->lost_reason)->toBe('Chose a competitor');
});

it('assigns a lead to a user', function () {
    $lead = makeLead($this->company->id);
    $rep = User::create([
        'company_id' => $this->company->id,
        'name' => 'Rep Two',
        'email' => 'rep2@acme.test',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    $this->postJson(route('api.admin.crm.lead.assign', $lead), [
        'assigned_to_user_id' => $rep->id,
    ])->assertOk()->assertJsonPath('data.assigned_to_user_id', $rep->id);

    expect(CrmActivity::where('subject_id', $lead->id)
        ->where('type', CrmActivityTypeEnum::Assigned->value)->count())->toBe(1);
});

it('converts a lead to a customer preserving all history', function () {
    $lead = makeLead($this->company->id);

    $lead->notes()->create(['body' => 'Spoke at trade show']);
    $tag = Tag::create(['name' => 'Hot Lead']);
    $lead->tags()->attach($tag);
    ContactPerson::create([
        'company_id' => $this->company->id,
        'party_id' => $lead->id,
        'name' => 'Jane Buyer',
    ]);
    $lead->activities()->create([
        'type' => CrmActivityTypeEnum::NoteAdded->value,
        'description' => 'note',
        'occurred_at' => now(),
    ]);

    $response = $this->postJson(route('api.admin.crm.lead.convert', $lead));

    $response->assertOk()->assertJsonPath('data.type', 'customer');

    $lead->refresh();
    expect($lead->type)->toBe(PartyTypeEnum::CUSTOMER)
        ->and($lead->leadProfile->refresh()->status)->toBe(CrmLeadStatusEnum::Converted)
        ->and($lead->leadProfile->converted_at)->not->toBeNull()
        ->and($lead->notes()->count())->toBe(1)
        ->and($lead->tags()->count())->toBe(1)
        ->and($lead->contactPersons()->count())->toBe(1);

    expect(CrmActivity::where('subject_id', $lead->id)
        ->where('type', CrmActivityTypeEnum::Converted->value)->count())->toBe(1);
});

it('does not convert a party that is not a lead', function () {
    $customer = Party::create([
        'company_id' => $this->company->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Already Customer',
        'code' => 'CUST-9999',
    ]);

    $this->postJson(route('api.admin.crm.lead.convert', $customer))->assertStatus(422);
});

it('manages contact persons for a party', function () {
    $lead = makeLead($this->company->id);

    $store = $this->postJson(route('api.admin.contact-person.store'), [
        'party_id' => $lead->id,
        'name' => 'Primary Contact',
        'is_primary' => true,
    ]);
    $store->assertCreated()->assertJsonPath('data.name', 'Primary Contact');

    $contactId = $store->json('data.id');

    $this->putJson(route('api.admin.contact-person.update', $contactId), [
        'party_id' => $lead->id,
        'name' => 'Updated Contact',
    ])->assertOk()->assertJsonPath('data.name', 'Updated Contact');

    $this->getJson(route('api.admin.contact-person.index', ['party_id' => $lead->id]))
        ->assertOk()->assertJsonCount(1, 'data');

    $this->deleteJson(route('api.admin.contact-person.destroy', $contactId))->assertOk();

    expect(ContactPerson::count())->toBe(0);
});

it('forbids listing leads without permission', function () {
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

    $this->getJson(route('api.admin.lead.index'))->assertForbidden();
});

it('scopes leads to the active company', function () {
    makeLead($this->company->id);

    $other = makeCompany('Other Co', 'OTHR');
    TenantService::setCompanyId($other->id);

    expect(CrmLeadProfile::count())->toBe(0)
        ->and(Party::where('type', PartyTypeEnum::LEAD)->count())->toBe(0);
});

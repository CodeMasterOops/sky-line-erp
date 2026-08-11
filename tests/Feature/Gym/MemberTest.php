<?php

use App\Models\Party;
use App\Models\Member;
use App\Enums\PartyTypeEnum;
use App\Enums\MemberStatusEnum;
use App\Services\TenantService;
use Illuminate\Http\UploadedFile;
use App\Services\Gym\MemberService;
use Tests\Feature\Gym\GymTestSupport;
use Illuminate\Support\Facades\Storage;

/*
| Phase 5 — Member Management
| (docs/saas-modular-platform-and-gym-module-plan.md §4.1, §5).
|
| A member is a Party of type customer plus a gym profile. That is the decision
| the whole vertical rests on: it buys invoicing, receipts, AR ageing, tags,
| notes and the CRM timeline for free, and these tests pin it down.
*/

beforeEach(function () {
    ['company' => $this->company, 'branch' => $this->branch, 'user' => $this->user] = GymTestSupport::makeGymCompany();

    $this->service = app(MemberService::class);
});

it('registers a member as a customer party plus a gym profile', function () {
    $response = $this->postJson('/api/admin/gym/member', [
        'name' => 'Ram Bahadur',
        'phone' => '9800000001',
        'email' => 'ram@example.test',
        'gender' => 'male',
        'joined_on' => now()->toDateString(),
    ])->assertSuccessful();

    $member = Member::query()->with('party')->sole();

    expect($response->json('data.name'))->toBe('Ram Bahadur')
        ->and($member->party->type)->toBe(PartyTypeEnum::CUSTOMER)
        ->and($member->party->name)->toBe('Ram Bahadur')
        ->and($member->party->phone)->toBe('9800000001')
        ->and($member->status)->toBe(MemberStatusEnum::Inactive);
});

it('gives every member a unique member id', function () {
    $first = $this->service->create(['name' => 'First Member']);
    $second = $this->service->create(['name' => 'Second Member']);

    expect($first->member_code)->toBe('MEM-00001')
        ->and($second->member_code)->toBe('MEM-00002');
});

it('offers the next member id before the form is submitted', function () {
    $this->service->create(['name' => 'Existing Member']);

    expect($this->getJson('/api/admin/gym/member/next-code')->json('data.member_code'))
        ->toBe('MEM-00002');
});

it('keeps member ids unique per company', function () {
    $this->service->create(['name' => 'Company One Member']);

    $other = GymTestSupport::makeGymCompany('Other Gym', 'OTHER');
    $otherMember = app(MemberService::class)->create(['name' => 'Company Two Member']);

    // Numbering restarts per company, so both are MEM-00001 without colliding.
    expect($otherMember->member_code)->toBe('MEM-00001')
        ->and($otherMember->company_id)->toBe($other['company']->id);
});

it('rejects a duplicate member id', function () {
    $this->service->create(['name' => 'First', 'member_code' => 'MEM-00001']);

    $this->postJson('/api/admin/gym/member', [
        'name' => 'Second',
        'member_code' => 'MEM-00001',
    ])->assertStatus(422)->assertJsonValidationErrors('member_code');
});

it('requires a name', function () {
    $this->postJson('/api/admin/gym/member', ['phone' => '9800000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('rejects a birth date in the future', function () {
    $this->postJson('/api/admin/gym/member', [
        'name' => 'Time Traveller',
        'date_of_birth' => now()->addDay()->toDateString(),
    ])->assertStatus(422)->assertJsonValidationErrors('date_of_birth');
});

it('rejects a phone number another party already uses', function () {
    $this->service->create(['name' => 'First', 'phone' => '9800000001']);

    $this->postJson('/api/admin/gym/member', [
        'name' => 'Second',
        'phone' => '9800000001',
    ])->assertStatus(422)->assertJsonValidationErrors('phone');
});

it('stores the profile fields a gym actually needs', function () {
    $member = $this->service->create([
        'name' => 'Sita Sharma',
        'date_of_birth' => '1995-04-12',
        'gender' => 'female',
        'blood_group' => 'O+',
        'emergency_contact_name' => 'Hari Sharma',
        'emergency_contact_phone' => '9800000002',
        'height_cm' => 165.5,
        'weight_kg' => 58.2,
        'medical_notes' => 'Mild asthma.',
    ]);

    expect($member->date_of_birth->toDateString())->toBe('1995-04-12')
        ->and($member->blood_group->value)->toBe('O+')
        ->and($member->emergency_contact_phone)->toBe('9800000002')
        ->and($member->height_cm)->toBe(165.5)
        ->and($member->medical_notes)->toBe('Mild asthma.');
});

it('rejects invalid gender and blood group values', function () {
    $this->postJson('/api/admin/gym/member', [
        'name' => 'Bad Enums',
        'gender' => 'unknown',
        'blood_group' => 'Z+',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['gender', 'blood_group']);
});

it('updates the party and the profile from one payload', function () {
    $member = $this->service->create(['name' => 'Old Name', 'phone' => '9800000003']);

    $this->putJson("/api/admin/gym/member/{$member->id}", [
        'name' => 'New Name',
        'phone' => '9800000004',
        'occupation' => 'Teacher',
    ])->assertSuccessful();

    $member->refresh()->load('party');

    expect($member->party->name)->toBe('New Name')
        ->and($member->party->phone)->toBe('9800000004')
        ->and($member->occupation)->toBe('Teacher');
});

it('stores a member photo', function () {
    Storage::fake('public');

    $member = $this->service->create(['name' => 'Photo Member']);

    $this->postJson("/api/admin/gym/member/{$member->id}/photo", [
        'photo' => UploadedFile::fake()->image('member.jpg'),
    ])->assertSuccessful();

    $member->refresh();

    expect($member->photo)->not->toBeNull()
        ->and($member->photo_url)->not->toBe('');

    Storage::disk('public')->assertExists($member->photo);
});

it('searches by name, phone and member id', function () {
    $this->service->create(['name' => 'Ram Bahadur', 'phone' => '9811111111']);
    $this->service->create(['name' => 'Sita Sharma', 'phone' => '9822222222']);

    expect($this->getJson('/api/admin/gym/member?search=Sita')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/admin/gym/member?search=9811111111')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/admin/gym/member?search=MEM-00001')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/admin/gym/member?search=nobody')->json('data'))->toHaveCount(0);
});

it('filters by status', function () {
    $this->service->create(['name' => 'Inactive Member']);
    $active = $this->service->create(['name' => 'Active Member']);
    $active->update(['status' => MemberStatusEnum::Active]);

    expect($this->getJson('/api/admin/gym/member?status=active')->json('data'))->toHaveCount(1);
});

it('deletes a member and the party behind them', function () {
    $member = $this->service->create(['name' => 'Leaving Member']);
    $partyId = $member->party_id;

    $this->deleteJson("/api/admin/gym/member/{$member->id}")->assertSuccessful();

    expect(Member::query()->whereKey($member->id)->exists())->toBeFalse()
        ->and(Party::query()->whereKey($partyId)->exists())->toBeFalse()
        // Soft deletes throughout: the financial history behind the party is
        // never actually removed.
        ->and(Member::withTrashed()->whereKey($member->id)->exists())->toBeTrue()
        ->and(Party::withTrashed()->whereKey($partyId)->exists())->toBeTrue();
});

it('keeps one company\'s members away from another', function () {
    $this->service->create(['name' => 'Company One Member']);

    GymTestSupport::makeGymCompany('Other Gym', 'OTHER');

    expect($this->getJson('/api/admin/gym/member')->json('data'))->toHaveCount(0);
});

it('scopes members to the active branch', function () {
    $member = $this->service->create(['name' => 'Head Office Member']);

    expect($member->branch_id)->toBe($this->branch->id);

    $otherBranch = App\Models\Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Second Branch',
        'code' => 'BR2',
    ]);

    TenantService::setBranchId($otherBranch->id);

    expect(Member::query()->count())->toBe(0);
});

it('records a member in the audit trail', function () {
    $member = $this->service->create(['name' => 'Audited Member']);

    expect(App\Models\AuditLog::query()->where('auditable_id', $member->id)->exists())->toBeTrue();
});

it('blocks the module\'s routes when gym is switched off', function () {
    GymTestSupport::moduleService()->disable($this->company, 'gym');

    $this->getJson('/api/admin/gym/member')
        ->assertForbidden()
        ->assertJsonPath('module', 'gym');
});

it('keeps every member when the module is switched off and back on', function () {
    $this->service->create(['name' => 'Persistent Member']);

    $service = GymTestSupport::moduleService();
    $service->disable($this->company, 'gym');

    // The rows are untouched while the doors are closed.
    expect(Member::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(1);

    $service->enable($this->company, 'gym');

    expect($this->getJson('/api/admin/gym/member')->json('data'))->toHaveCount(1);
});

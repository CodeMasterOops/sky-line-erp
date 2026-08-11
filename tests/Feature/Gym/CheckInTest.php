<?php

use App\Models\MemberCheckIn;
use App\Enums\MembershipStatusEnum;
use App\Services\Gym\MemberService;
use App\Services\Gym\CheckInService;
use Tests\Feature\Gym\GymTestSupport;
use App\Services\Gym\MembershipService;
use App\Services\Gym\MembershipPlanService;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 7 — the front-desk visit log
| (docs/saas-modular-platform-and-gym-module-plan.md §5, member_check_ins).
|
| Deliberately not HR Attendance: this is a member walking in, with no leave or
| payroll consequences. A lapsed member is still recorded rather than turned
| away — who to admit is the gym's policy, not the software's.
*/

beforeEach(function () {
    ['company' => $this->company, 'branch' => $this->branch] = GymTestSupport::makeGymCompany();

    app(CompanyModuleService::class)->updateSettings($this->company, 'gym', [
        'auto_invoice_on_assignment' => false,
    ]);

    $this->checkIns = app(CheckInService::class);
    $this->member = app(MemberService::class)->create(['name' => 'Ram Bahadur', 'phone' => '9800000001']);
    $this->plan = app(MembershipPlanService::class)->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);
});

it('records a visit', function () {
    $response = $this->postJson('/api/admin/gym/check-in', [
        'member_id' => $this->member->id,
    ])->assertSuccessful();

    expect($response->json('data.member_name'))->toBe('Ram Bahadur')
        ->and(MemberCheckIn::query()->count())->toBe(1);
});

it('attaches the visit to the running term', function () {
    $membership = app(MembershipService::class)->assign($this->member, $this->plan);

    $checkIn = $this->checkIns->checkIn($this->member);

    expect($checkIn->membership_id)->toBe($membership->id)
        ->and($checkIn->without_membership ?? false)->toBeFalsy();
});

it('still records a member with no running term', function () {
    $checkIn = $this->checkIns->checkIn($this->member);

    expect($checkIn->membership_id)->toBeNull();

    $listed = $this->getJson('/api/admin/gym/check-in')->json('data');

    // Flagged so the desk knows to ask about a renewal.
    expect($listed[0]['without_membership'])->toBeTrue();
});

it('refuses a second check-in while one is open', function () {
    $this->checkIns->checkIn($this->member);

    $this->postJson('/api/admin/gym/check-in', ['member_id' => $this->member->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('member_id');
});

it('allows another visit once the last one is closed', function () {
    $first = $this->checkIns->checkIn($this->member);
    $this->checkIns->checkOut($first);

    $second = $this->checkIns->checkIn($this->member);

    expect($second->id)->not->toBe($first->id)
        ->and(MemberCheckIn::query()->count())->toBe(2);
});

it('checks a member out and records how long they stayed', function () {
    $checkIn = $this->checkIns->checkIn($this->member, ['checked_in_at' => now()->subMinutes(75)]);

    $response = $this->postJson("/api/admin/gym/check-in/{$checkIn->id}/check-out")->assertSuccessful();

    expect($response->json('data.duration_minutes'))->toBe(75);
});

it('refuses to check out twice', function () {
    $checkIn = $this->checkIns->checkIn($this->member);
    $this->checkIns->checkOut($checkIn);

    $this->postJson("/api/admin/gym/check-in/{$checkIn->fresh()->id}/check-out")->assertStatus(422);
});

it('refuses a check-out before the check-in', function () {
    $checkIn = $this->checkIns->checkIn($this->member);

    $this->postJson("/api/admin/gym/check-in/{$checkIn->id}/check-out", [
        'checked_out_at' => now()->subHour()->toDateTimeString(),
    ])->assertStatus(422);
});

it('looks a member up by member id', function () {
    app(MembershipService::class)->assign($this->member, $this->plan);

    $response = $this->postJson('/api/admin/gym/check-in/lookup', [
        'identifier' => $this->member->member_code,
    ])->assertSuccessful();

    expect($response->json('data.member.name'))->toBe('Ram Bahadur')
        ->and($response->json('data.membership.plan_name'))->toBe('Monthly')
        ->and($response->json('data.membership.status'))->toBe('active');
});

it('looks a member up by phone number', function () {
    $response = $this->postJson('/api/admin/gym/check-in/lookup', [
        'identifier' => '9800000001',
    ])->assertSuccessful();

    expect($response->json('data.member.member_code'))->toBe($this->member->member_code);
});

it('says so when nobody matches', function () {
    $this->postJson('/api/admin/gym/check-in/lookup', ['identifier' => 'NOPE'])
        ->assertNotFound();
});

it('reports an open visit on lookup so the desk can check them out', function () {
    $this->checkIns->checkIn($this->member);

    $response = $this->postJson('/api/admin/gym/check-in/lookup', [
        'identifier' => $this->member->member_code,
    ])->assertSuccessful();

    expect($response->json('data.open_check_in.id'))->not->toBeNull();
});

it('filters the log by date', function () {
    MemberCheckIn::factory()->at(now()->subDays(2)->toDateTimeString())->create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branch->id,
        'member_id' => $this->member->id,
    ]);
    $this->checkIns->checkIn($this->member);

    expect($this->getJson('/api/admin/gym/check-in?date='.now()->toDateString())->json('data'))
        ->toHaveCount(1);
});

it('keeps one company\'s visits away from another', function () {
    $this->checkIns->checkIn($this->member);

    GymTestSupport::makeGymCompany('Other Gym', 'OTHER');

    expect($this->getJson('/api/admin/gym/check-in')->json('data'))->toHaveCount(0);
});

it('blocks check-in routes when the gym module is off', function () {
    GymTestSupport::moduleService()->disable($this->company, 'gym');

    $this->getJson('/api/admin/gym/check-in')
        ->assertForbidden()
        ->assertJsonPath('module', 'gym');
});

it('keeps the visit log when the module is switched off and back on', function () {
    $this->checkIns->checkIn($this->member);

    $modules = GymTestSupport::moduleService();
    $modules->disable($this->company, 'gym');

    expect(MemberCheckIn::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(1);

    $modules->enable($this->company, 'gym');

    expect($this->getJson('/api/admin/gym/check-in')->json('data'))->toHaveCount(1);
});

it('does not confuse a member visit with staff attendance', function () {
    $this->checkIns->checkIn($this->member);

    // The HR module's Attendance table is untouched by a gym check-in.
    expect(App\Models\Attendance::query()->count())->toBe(0)
        ->and(MemberCheckIn::query()->count())->toBe(1);
})->skip(fn (): bool => ! class_exists(App\Models\Attendance::class), 'HR module not present.');

it('links the visit to a frozen term as well as an active one', function () {
    $membership = app(MembershipService::class)->assign($this->member, $this->plan);
    $membership->update(['status' => MembershipStatusEnum::Frozen]);

    $checkIn = $this->checkIns->checkIn($this->member);

    expect($checkIn->membership_id)->toBe($membership->id);
});

<?php

use App\Enums\MemberStatusEnum;
use App\Enums\MembershipStatusEnum;
use App\Services\Gym\MemberService;
use Tests\Feature\Gym\GymTestSupport;
use App\Services\Gym\MembershipService;
use App\Services\Gym\MembershipPlanService;
use App\Services\Gym\MembershipExpiryService;
use App\Services\Gym\MembershipFreezeService;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 7 — freezing and resuming a term.
|
| The promise to the member: a freeze gives back exactly the days lost, so
| nobody pays for time they could not use. The plan's `max_freeze_days` caps how
| much of that a gym is prepared to give.
*/

beforeEach(function () {
    ['company' => $this->company, 'branch' => $this->branch] = GymTestSupport::makeGymCompany();

    app(CompanyModuleService::class)->updateSettings($this->company, 'gym', [
        'auto_invoice_on_assignment' => false,
    ]);

    $this->freezes = app(MembershipFreezeService::class);
    $this->member = app(MemberService::class)->create(['name' => 'Ram Bahadur']);

    $this->plan = app(MembershipPlanService::class)->create([
        'name' => 'Monthly',
        'preset' => 'monthly',
        'price' => 2000,
        'max_freeze_days' => 30,
    ]);

    $this->membership = app(MembershipService::class)->assign($this->member, $this->plan, [
        'start_date' => now()->toDateString(),
    ]);
});

it('freezes a running term', function () {
    $frozen = $this->freezes->freeze($this->membership, ['reason' => 'Travelling.']);

    expect($frozen->status)->toBe(MembershipStatusEnum::Frozen)
        ->and($frozen->freezes)->toHaveCount(1)
        ->and($frozen->runningFreeze()->reason)->toBe('Travelling.');
});

it('marks the member frozen too', function () {
    $this->freezes->freeze($this->membership);

    expect($this->member->fresh()->status)->toBe(MemberStatusEnum::Frozen);
});

it('gives back exactly the days lost on resume', function () {
    $originalEnd = $this->membership->end_date->copy();

    $this->freezes->freeze($this->membership, ['from_date' => now()->toDateString()]);
    $resumed = $this->freezes->resume($this->membership->fresh(), [
        'to_date' => now()->addDays(9)->toDateString(),
    ]);

    // 10 days inclusive of both ends.
    expect($resumed->freeze_days_used)->toBe(10)
        ->and($resumed->end_date->toDateString())->toBe($originalEnd->addDays(10)->toDateString())
        ->and($resumed->status)->toBe(MembershipStatusEnum::Active);
});

it('counts a same-day freeze and resume as one day', function () {
    $originalEnd = $this->membership->end_date->copy();

    $this->freezes->freeze($this->membership);
    $resumed = $this->freezes->resume($this->membership->fresh());

    expect($resumed->freeze_days_used)->toBe(1)
        ->and($resumed->end_date->toDateString())->toBe($originalEnd->addDay()->toDateString());
});

it('caps the extension at the plan\'s allowance', function () {
    $plan = app(MembershipPlanService::class)->create([
        'name' => 'Short Freeze',
        'preset' => 'yearly',
        'price' => 18000,
        'max_freeze_days' => 5,
    ]);

    $member = app(MemberService::class)->create(['name' => 'Sita Sharma']);
    $membership = app(MembershipService::class)->assign($member, $plan);
    $originalEnd = $membership->end_date->copy();

    $this->freezes->freeze($membership);
    $resumed = $this->freezes->resume($membership->fresh(), [
        'to_date' => now()->addDays(29)->toDateString(),
    ]);

    // 30 days away, but the plan only allows 5.
    expect($resumed->freeze_days_used)->toBe(5)
        ->and($resumed->end_date->toDateString())->toBe($originalEnd->addDays(5)->toDateString());
});

it('refuses to freeze on a plan that does not allow it', function () {
    $plan = app(MembershipPlanService::class)->create([
        'name' => 'No Freeze',
        'preset' => 'monthly',
        'price' => 2000,
        'max_freeze_days' => 0,
    ]);

    $member = app(MemberService::class)->create(['name' => 'No Freeze Member']);
    $membership = app(MembershipService::class)->assign($member, $plan);

    expect(fn () => $this->freezes->freeze($membership))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'does not allow freezing');
});

it('refuses to freeze once the allowance is spent', function () {
    $this->freezes->freeze($this->membership);
    $this->freezes->resume($this->membership->fresh(), ['to_date' => now()->addDays(29)->toDateString()]);

    expect(fn () => $this->freezes->freeze($this->membership->fresh()))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'full freeze allowance');
});

it('refuses to freeze anything that is not running', function () {
    $this->freezes->freeze($this->membership);

    expect(fn () => $this->freezes->freeze($this->membership->fresh()))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'Only an active membership');
});

it('refuses to resume a term that is not frozen', function () {
    expect(fn () => $this->freezes->resume($this->membership))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'not frozen');
});

it('refuses a resume date before the freeze began', function () {
    $this->freezes->freeze($this->membership, ['from_date' => now()->toDateString()]);

    expect(fn () => $this->freezes->resume($this->membership->fresh(), [
        'to_date' => now()->subDays(3)->toDateString(),
    ]))->toThrow(Illuminate\Validation\ValidationException::class, 'cannot be before');
});

it('does not expire a term that is frozen when its end date passes', function () {
    // Freeze while the term is still running, then let its end date go by. The
    // nightly sweep must leave it alone — that is the whole point of freezing.
    $this->freezes->freeze($this->membership);
    $this->membership->fresh()->update(['end_date' => now()->subDay()->toDateString()]);

    app(MembershipExpiryService::class)->expireDueMemberships($this->company);

    expect($this->membership->fresh()->status)->toBe(MembershipStatusEnum::Frozen);
});

it('refuses to freeze a term that has already ended', function () {
    $this->membership->update(['end_date' => now()->subDay()->toDateString()]);

    expect(fn () => $this->freezes->freeze($this->membership->fresh()))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'cannot start after the membership ends');
});

it('freezes and resumes through the api', function () {
    $this->postJson("/api/admin/gym/membership/{$this->membership->id}/freeze", [
        'reason' => 'Injury.',
    ])->assertSuccessful()->assertJsonPath('data.status', 'frozen');

    $this->postJson("/api/admin/gym/membership/{$this->membership->id}/resume")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active');
});

it('keeps a record of every freeze taken', function () {
    $this->freezes->freeze($this->membership);
    $this->freezes->resume($this->membership->fresh(), ['to_date' => now()->addDays(2)->toDateString()]);
    $this->freezes->freeze($this->membership->fresh());
    $this->freezes->resume($this->membership->fresh(), ['to_date' => now()->addDay()->toDateString()]);

    expect($this->membership->fresh()->freezes)->toHaveCount(2)
        ->and($this->membership->fresh()->freezes->sum('days'))->toBe(5);
});

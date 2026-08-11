<?php

use App\Models\Membership;
use App\Enums\MemberStatusEnum;
use App\Services\TenantService;
use App\Enums\MembershipStatusEnum;
use App\Services\Gym\MemberService;
use Tests\Feature\Gym\GymTestSupport;
use App\Models\CompanyNotificationSetting;
use App\Services\Gym\MembershipPlanService;
use Illuminate\Support\Facades\Notification;
use App\Services\Gym\MembershipExpiryService;
use App\Services\Modules\CompanyModuleService;
use App\Notifications\MembershipExpiredNotification;
use App\Notifications\MembershipExpiryReminderNotification;

/*
| Phase 6 — automatic expiry and expiry reminders
| (docs/saas-modular-platform-and-gym-module-plan.md §6.6).
|
| Both sweeps run per company and must be safe to re-run: expiry only moves a
| term whose last day has genuinely passed, and each reminder offset is recorded
| on the membership so the same nudge is never sent twice.
*/

beforeEach(function () {
    ['company' => $this->company, 'branch' => $this->branch, 'user' => $this->user] = GymTestSupport::makeGymCompany();

    $this->plans = app(MembershipPlanService::class);
    $this->expiry = app(MembershipExpiryService::class);

    app(CompanyModuleService::class)->updateSettings($this->company, 'gym', [
        'auto_invoice_on_assignment' => false,
    ]);

    $this->plan = $this->plans->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);

    $this->makeTerm = function (string $endDate, array $overrides = []) {
        $member = app(MemberService::class)->create(['name' => 'Member '.fake()->unique()->numberBetween(1, 9999)]);

        return Membership::factory()->expiredOn($endDate)->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $this->plan->id,
            'branch_id' => $this->branch->id,
            'status' => MembershipStatusEnum::Active,
        ], $overrides));
    };
});

it('expires a term whose last day has passed', function () {
    $membership = ($this->makeTerm)(now()->subDay()->toDateString());

    expect($this->expiry->expireDueMemberships($this->company))->toBe(1)
        ->and($membership->fresh()->status)->toBe(MembershipStatusEnum::Expired)
        ->and($membership->fresh()->expired_at)->not->toBeNull();
});

it('leaves a term running on its last day', function () {
    $membership = ($this->makeTerm)(now()->toDateString());

    expect($this->expiry->expireDueMemberships($this->company))->toBe(0)
        ->and($membership->fresh()->status)->toBe(MembershipStatusEnum::Active);
});

it('leaves a future term alone', function () {
    $membership = ($this->makeTerm)(now()->addWeek()->toDateString());

    $this->expiry->expireDueMemberships($this->company);

    expect($membership->fresh()->status)->toBe(MembershipStatusEnum::Active);
});

it('honours the plan\'s grace days before expiring', function () {
    $graced = $this->plans->create([
        'name' => 'With Grace',
        'preset' => 'monthly',
        'price' => 2000,
        'grace_days' => 5,
    ]);

    $withinGrace = ($this->makeTerm)(now()->subDays(3)->toDateString(), ['membership_plan_id' => $graced->id]);
    $pastGrace = ($this->makeTerm)(now()->subDays(6)->toDateString(), ['membership_plan_id' => $graced->id]);

    $this->expiry->expireDueMemberships($this->company);

    expect($withinGrace->fresh()->status)->toBe(MembershipStatusEnum::Active)
        ->and($pastGrace->fresh()->status)->toBe(MembershipStatusEnum::Expired);
});

it('stands the member down when their term expires', function () {
    $membership = ($this->makeTerm)(now()->subDay()->toDateString());
    $membership->member->update(['status' => MemberStatusEnum::Active]);

    $this->expiry->expireDueMemberships($this->company);

    expect($membership->member->fresh()->status)->toBe(MemberStatusEnum::Expired);
});

it('is safe to run twice on the same day', function () {
    ($this->makeTerm)(now()->subDay()->toDateString());

    expect($this->expiry->expireDueMemberships($this->company))->toBe(1)
        ->and($this->expiry->expireDueMemberships($this->company))->toBe(0);
});

it('never touches a cancelled term', function () {
    $membership = ($this->makeTerm)(now()->subMonth()->toDateString(), [
        'status' => MembershipStatusEnum::Cancelled,
        'cancelled_at' => now()->subMonth(),
    ]);

    $this->expiry->expireDueMemberships($this->company);

    expect($membership->fresh()->status)->toBe(MembershipStatusEnum::Cancelled);
});

it('runs from the scheduled command', function () {
    $membership = ($this->makeTerm)(now()->subDay()->toDateString());

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->artisan('gym:process-membership-expiry')->assertSuccessful();

    expect($membership->fresh()->status)->toBe(MembershipStatusEnum::Expired);
});

it('skips companies that do not run the gym module', function () {
    $membership = ($this->makeTerm)(now()->subDay()->toDateString());

    GymTestSupport::moduleService()->disable($this->company, 'gym');
    TenantService::setCompanyId(null);

    $this->artisan('gym:process-membership-expiry')
        ->expectsOutputToContain('No company has the Gym module enabled.')
        ->assertSuccessful();

    expect($membership->fresh()->status)->toBe(MembershipStatusEnum::Active);
});

it('reminds staff at each configured offset', function () {
    Notification::fake();

    ($this->makeTerm)(now()->addDays(7)->toDateString());
    ($this->makeTerm)(now()->addDays(3)->toDateString());
    ($this->makeTerm)(now()->addDays(1)->toDateString());
    ($this->makeTerm)(now()->addDays(5)->toDateString());

    // Default offsets are 7, 3 and 1 — the term ending in 5 days is not due a
    // nudge today.
    expect($this->expiry->dispatchReminders($this->company))->toBe(3);

    Notification::assertSentTo($this->user, MembershipExpiryReminderNotification::class);
});

it('does not send the same reminder twice', function () {
    Notification::fake();

    $membership = ($this->makeTerm)(now()->addDays(3)->toDateString());

    expect($this->expiry->dispatchReminders($this->company))->toBe(1)
        ->and($this->expiry->dispatchReminders($this->company))->toBe(0)
        ->and($membership->fresh()->reminders_sent)->toBe([3]);
});

it('still reminds at the next offset after an earlier one', function () {
    Notification::fake();

    $membership = ($this->makeTerm)(now()->addDays(3)->toDateString());
    $this->expiry->dispatchReminders($this->company);

    // Two days later the same term is one day out, which is a separate offset.
    $membership->update(['end_date' => now()->addDay()->toDateString()]);

    expect($this->expiry->dispatchReminders($this->company))->toBe(1)
        ->and($membership->fresh()->reminders_sent)->toBe([3, 1]);
});

it('respects the company\'s own reminder offsets', function () {
    Notification::fake();

    CompanyNotificationSetting::query()->updateOrCreate(
        ['company_id' => $this->company->id],
        ['membership_expiry_reminder' => true, 'membership_expiry_reminder_days' => [14]],
    );

    ($this->makeTerm)(now()->addDays(14)->toDateString());
    ($this->makeTerm)(now()->addDays(7)->toDateString());

    expect($this->expiry->dispatchReminders($this->company))->toBe(1);
});

it('sends nothing when the company switched reminders off', function () {
    Notification::fake();

    CompanyNotificationSetting::query()->updateOrCreate(
        ['company_id' => $this->company->id],
        ['membership_expiry_reminder' => false],
    );

    ($this->makeTerm)(now()->addDays(3)->toDateString());

    expect($this->expiry->dispatchReminders($this->company))->toBe(0);

    Notification::assertNothingSent();
});

it('does not remind about an expired or cancelled term', function () {
    Notification::fake();

    ($this->makeTerm)(now()->addDays(3)->toDateString(), ['status' => MembershipStatusEnum::Expired]);
    ($this->makeTerm)(now()->addDays(3)->toDateString(), ['status' => MembershipStatusEnum::Cancelled]);

    expect($this->expiry->dispatchReminders($this->company))->toBe(0);
});

it('alerts staff after an expiry sweep', function () {
    Notification::fake();

    ($this->makeTerm)(now()->subDay()->toDateString());

    $this->expiry->expireDueMemberships($this->company);

    Notification::assertSentTo($this->user, MembershipExpiredNotification::class);
});

it('sends no expiry alert when nothing expired', function () {
    Notification::fake();

    ($this->makeTerm)(now()->addWeek()->toDateString());

    $this->expiry->expireDueMemberships($this->company);

    Notification::assertNothingSent();
});

it('runs reminders from the scheduled command', function () {
    Notification::fake();

    ($this->makeTerm)(now()->addDays(3)->toDateString());

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->artisan('gym:dispatch-membership-reminders')->assertSuccessful();

    Notification::assertSentTo($this->user, MembershipExpiryReminderNotification::class);
});

it('keeps one company\'s sweep out of another\'s', function () {
    $mine = ($this->makeTerm)(now()->subDay()->toDateString());

    $other = GymTestSupport::makeGymCompany('Other Gym', 'OTHER');
    $otherPlan = app(MembershipPlanService::class)->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 1]);
    $otherMember = app(MemberService::class)->create(['name' => 'Other Member']);
    $theirs = Membership::factory()->expiredOn(now()->subDay()->toDateString())->create([
        'member_id' => $otherMember->id,
        'membership_plan_id' => $otherPlan->id,
        'branch_id' => $other['branch']->id,
        'status' => MembershipStatusEnum::Active,
    ]);

    expect($this->expiry->expireDueMemberships($this->company))->toBe(1)
        ->and($mine->fresh()->status)->toBe(MembershipStatusEnum::Expired)
        ->and($theirs->fresh()->status)->toBe(MembershipStatusEnum::Active);
});

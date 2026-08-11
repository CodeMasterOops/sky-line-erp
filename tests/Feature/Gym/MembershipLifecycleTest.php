<?php

use App\Models\Membership;
use App\Enums\MemberStatusEnum;
use App\Enums\MembershipStatusEnum;
use App\Services\Gym\MemberService;
use Tests\Feature\Gym\GymTestSupport;
use App\Services\Gym\MembershipService;
use App\Services\Gym\MembershipPlanService;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 6 — assignment, renewal and cancellation
| (docs/saas-modular-platform-and-gym-module-plan.md §6.2–§6.4).
|
| A term is never edited in place. Renewing writes a new row chained through
| `renewed_from_id`, so history is immutable and each invoice stays attached to
| the period it paid for.
*/

beforeEach(function () {
    ['company' => $this->company, 'branch' => $this->branch] = GymTestSupport::makeGymCompany();

    $this->plans = app(MembershipPlanService::class);
    $this->memberships = app(MembershipService::class);

    $this->member = app(MemberService::class)->create(['name' => 'Ram Bahadur', 'phone' => '9800000001']);
    $this->plan = $this->plans->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);

    // Billing is exercised on its own in MembershipInvoiceTest; the lifecycle
    // tests keep the noise down by leaving it off.
    app(CompanyModuleService::class)->updateSettings($this->company, 'gym', [
        'auto_invoice_on_assignment' => false,
    ]);
});

it('assigns a term and computes its end date', function () {
    $membership = $this->memberships->assign($this->member, $this->plan, ['start_date' => '2026-01-01']);

    expect($membership->start_date->toDateString())->toBe('2026-01-01')
        ->and($membership->end_date->toDateString())->toBe('2026-01-31')
        ->and($membership->status)->toBe(MembershipStatusEnum::Active)
        ->and($membership->membership_no)->toBe('MSHIP-00001');
});

it('activates the member when a term is assigned', function () {
    expect($this->member->status)->toBe(MemberStatusEnum::Inactive);

    $this->memberships->assign($this->member, $this->plan);

    expect($this->member->fresh()->status)->toBe(MemberStatusEnum::Active);
});

it('assigns through the api', function () {
    $response = $this->postJson('/api/admin/gym/membership', [
        'member_id' => $this->member->id,
        'membership_plan_id' => $this->plan->id,
    ])->assertSuccessful();

    expect($response->json('data.status'))->toBe('active')
        ->and($response->json('data.member_name'))->toBe('Ram Bahadur');
});

it('refuses a second running term for the same member', function () {
    $this->memberships->assign($this->member, $this->plan);

    $this->postJson('/api/admin/gym/membership', [
        'member_id' => $this->member->id,
        'membership_plan_id' => $this->plan->id,
    ])->assertStatus(422)->assertJsonValidationErrors('member_id');
});

it('allows a second term when the company opts in', function () {
    app(CompanyModuleService::class)->updateSettings($this->company, 'gym', [
        'allow_multiple_active_memberships' => true,
    ]);

    $this->memberships->assign($this->member, $this->plan);
    $this->memberships->assign($this->member, $this->plan);

    expect(Membership::query()->where('member_id', $this->member->id)->current()->count())->toBe(2);
});

it('refuses to sell an inactive plan', function () {
    $this->plan->update(['is_active' => false]);

    expect(fn () => $this->memberships->assign($this->member, $this->plan->fresh()))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'not active');
});

it('charges the joining fee once, on the first term only', function () {
    $plan = $this->plans->create([
        'name' => 'With Joining Fee',
        'preset' => 'monthly',
        'price' => 2000,
        'joining_fee' => 500,
    ]);

    $first = $this->memberships->assign($this->member, $plan);

    expect($first->joining_fee)->toBe(500.0)
        ->and($first->payable_amount)->toBe(2500.0);

    $renewal = $this->memberships->renew($first);

    expect($renewal->joining_fee)->toBe(0.0)
        ->and($renewal->payable_amount)->toBe(2000.0);
});

it('applies a discount to the payable amount', function () {
    $membership = $this->memberships->assign($this->member, $this->plan, ['discount_amount' => 500]);

    expect($membership->payable_amount)->toBe(1500.0);
});

it('renews early without losing a single day', function () {
    $first = $this->memberships->assign($this->member, $this->plan, [
        'start_date' => now()->toDateString(),
    ]);

    $renewal = $this->memberships->renew($first);

    expect($renewal->start_date->toDateString())
        ->toBe($first->end_date->copy()->addDay()->toDateString())
        ->and($renewal->renewed_from_id)->toBe($first->id)
        ->and($first->fresh()->status)->toBe(MembershipStatusEnum::Expired);
});

it('starts a lapsed renewal today by default', function () {
    $lapsed = Membership::factory()->expiredOn(now()->subDays(10)->toDateString())->create([
        'member_id' => $this->member->id,
        'membership_plan_id' => $this->plan->id,
        'branch_id' => $this->branch->id,
        'status' => MembershipStatusEnum::Expired,
    ]);

    $renewal = $this->memberships->renew($lapsed);

    expect($renewal->start_date->toDateString())->toBe(now()->toDateString());
});

it('continues a lapsed term when the company prefers that', function () {
    app(CompanyModuleService::class)->updateSettings($this->company, 'gym', [
        'lapsed_renewal_continues_term' => true,
    ]);

    $endDate = now()->subDays(10)->toDateString();
    $lapsed = Membership::factory()->expiredOn($endDate)->create([
        'member_id' => $this->member->id,
        'membership_plan_id' => $this->plan->id,
        'branch_id' => $this->branch->id,
        'status' => MembershipStatusEnum::Expired,
    ]);

    $renewal = $this->memberships->renew($lapsed);

    expect($renewal->start_date->toDateString())
        ->toBe(Carbon\Carbon::parse($endDate)->addDay()->toDateString());
});

it('renews onto a different plan, which is how an upgrade happens', function () {
    $yearly = $this->plans->create(['name' => 'Yearly', 'preset' => 'yearly', 'price' => 18000]);
    $first = $this->memberships->assign($this->member, $this->plan);

    $renewal = $this->memberships->renew($first, ['membership_plan_id' => $yearly->id]);

    expect($renewal->membership_plan_id)->toBe($yearly->id)
        ->and($renewal->price)->toBe(18000.0);
});

it('renews through the api', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);

    $response = $this->postJson("/api/admin/gym/membership/{$membership->id}/renew")->assertSuccessful();

    expect($response->json('data.renewed_from_id'))->toBe($membership->id);
});

it('refuses to renew a cancelled term', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);
    $this->memberships->cancel($membership, 'Moved away.');

    $this->postJson("/api/admin/gym/membership/{$membership->fresh()->id}/renew")
        ->assertStatus(422);
});

it('keeps the whole renewal chain as history', function () {
    $first = $this->memberships->assign($this->member, $this->plan);
    $second = $this->memberships->renew($first);
    $third = $this->memberships->renew($second);

    $history = $this->getJson("/api/admin/gym/member/{$this->member->id}/membership")->json('data');

    expect($history)->toHaveCount(3)
        ->and($third->renewed_from_id)->toBe($second->id)
        ->and($second->fresh()->renewed_from_id)->toBe($first->id)
        // Only the newest term is live; the earlier ones stay as history.
        ->and(Membership::query()->where('member_id', $this->member->id)->active()->count())->toBe(1);
});

it('cancels a term and stands the member down', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);

    $this->postJson("/api/admin/gym/membership/{$membership->id}/cancel", [
        'reason' => 'Relocating.',
    ])->assertSuccessful();

    expect($membership->fresh()->status)->toBe(MembershipStatusEnum::Cancelled)
        ->and($membership->fresh()->cancel_reason)->toBe('Relocating.')
        ->and($this->member->fresh()->status)->toBe(MemberStatusEnum::Cancelled);
});

it('lets a cancelled member start again with a fresh term', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);
    $this->memberships->cancel($membership);

    $fresh = $this->memberships->assign($this->member->fresh(), $this->plan);

    expect($fresh->status)->toBe(MembershipStatusEnum::Active)
        ->and($this->member->fresh()->status)->toBe(MemberStatusEnum::Active);
});

it('numbers memberships per company', function () {
    $second = app(MemberService::class)->create(['name' => 'Sita Sharma']);

    $first = $this->memberships->assign($this->member, $this->plan);
    $next = $this->memberships->assign($second, $this->plan);

    expect($first->membership_no)->toBe('MSHIP-00001')
        ->and($next->membership_no)->toBe('MSHIP-00002');
});

it('lists terms expiring within a window', function () {
    $soon = Membership::factory()->endingInDays(3)->create([
        'member_id' => $this->member->id,
        'membership_plan_id' => $this->plan->id,
        'branch_id' => $this->branch->id,
    ]);

    $later = Membership::factory()->endingInDays(45)->create([
        'member_id' => app(MemberService::class)->create(['name' => 'Later Member'])->id,
        'membership_plan_id' => $this->plan->id,
        'branch_id' => $this->branch->id,
    ]);

    $expiring = $this->getJson('/api/admin/gym/membership/expiring?days=7')->json('data');

    expect($expiring)->toHaveCount(1)
        ->and($expiring[0]['id'])->toBe($soon->id)
        ->and($later->fresh()->status)->toBe(MembershipStatusEnum::Active);
});

it('keeps one company\'s memberships away from another', function () {
    $this->memberships->assign($this->member, $this->plan);

    GymTestSupport::makeGymCompany('Other Gym', 'OTHER');

    expect($this->getJson('/api/admin/gym/membership')->json('data'))->toHaveCount(0);
});

it('blocks membership routes when the gym module is off', function () {
    GymTestSupport::moduleService()->disable($this->company, 'gym');

    $this->getJson('/api/admin/gym/membership')
        ->assertForbidden()
        ->assertJsonPath('module', 'gym');
});

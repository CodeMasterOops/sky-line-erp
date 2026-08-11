<?php

use App\Models\MemberCheckIn;
use App\Services\Gym\MemberService;
use Tests\Feature\Gym\GymTestSupport;
use App\Services\Gym\MembershipService;
use App\Services\Gym\MembershipPlanService;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 7 — the four gym reports.
|
| Revenue is read from the memberships rather than the invoices on purpose: it
| answers "what did we sell", which stays true whether or not a term was billed.
*/

beforeEach(function () {
    ['company' => $this->company, 'branch' => $this->branch] = GymTestSupport::makeGymCompany();

    app(CompanyModuleService::class)->updateSettings($this->company, 'gym', [
        'auto_invoice_on_assignment' => false,
    ]);

    $this->memberships = app(MembershipService::class);
    $this->monthly = app(MembershipPlanService::class)->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);
    $this->yearly = app(MembershipPlanService::class)->create(['name' => 'Yearly', 'preset' => 'yearly', 'price' => 18000]);

    $this->newMember = fn (string $name) => app(MemberService::class)->create(['name' => $name]);
});

it('summarises memberships by status and plan', function () {
    $this->memberships->assign(($this->newMember)('One'), $this->monthly);
    $this->memberships->assign(($this->newMember)('Two'), $this->monthly);
    $this->memberships->assign(($this->newMember)('Three'), $this->yearly);

    $data = $this->getJson('/api/admin/gym/report/membership-summary')->assertSuccessful()->json('data');

    expect($data['total'])->toBe(3)
        ->and(collect($data['by_status'])->firstWhere('status', 'active')['count'])->toBe(3)
        ->and(collect($data['by_plan'])->firstWhere('plan', 'Monthly')['count'])->toBe(2);
});

it('reports how much of the period was renewals', function () {
    $member = ($this->newMember)('Renewer');
    $first = $this->memberships->assign($member, $this->monthly);
    $this->memberships->renew($first);

    $this->memberships->assign(($this->newMember)('Newcomer'), $this->monthly);

    $data = $this->getJson('/api/admin/gym/report/renewals')->assertSuccessful()->json('data');

    expect($data['terms_sold'])->toBe(3)
        ->and($data['renewals'])->toBe(1)
        ->and($data['new_memberships'])->toBe(2)
        ->and($data['renewal_share'])->toBe(33.3)
        ->and($data['rows'])->toHaveCount(1);
});

it('reports revenue by plan, best first', function () {
    $this->memberships->assign(($this->newMember)('One'), $this->monthly);
    $this->memberships->assign(($this->newMember)('Two'), $this->monthly, ['discount_amount' => 500]);
    $this->memberships->assign(($this->newMember)('Three'), $this->yearly);

    $data = $this->getJson('/api/admin/gym/report/revenue-by-plan')->assertSuccessful()->json('data');

    expect($data['rows'][0]['plan'])->toBe('Yearly')
        ->and((float) $data['rows'][0]['net'])->toBe(18000.0)
        ->and((float) collect($data['rows'])->firstWhere('plan', 'Monthly')['net'])->toBe(3500.0)
        ->and((float) $data['total'])->toBe(21500.0);
});

it('leaves cancelled terms out of revenue', function () {
    $membership = $this->memberships->assign(($this->newMember)('Cancelled'), $this->monthly);
    $this->memberships->cancel($membership);
    $this->memberships->assign(($this->newMember)('Kept'), $this->monthly);

    expect((float) $this->getJson('/api/admin/gym/report/revenue-by-plan')->json('data.total'))->toBe(2000.0);
});

it('reports floor traffic', function () {
    $member = ($this->newMember)('Regular');
    $other = ($this->newMember)('Occasional');

    MemberCheckIn::factory()->at(now()->setTime(7, 0)->toDateTimeString())->create([
        'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'member_id' => $member->id,
    ]);
    MemberCheckIn::factory()->at(now()->setTime(7, 30)->toDateTimeString())->create([
        'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'member_id' => $other->id,
    ]);
    MemberCheckIn::factory()->at(now()->subDay()->setTime(18, 0)->toDateTimeString())->create([
        'company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'member_id' => $member->id,
    ]);

    $data = $this->getJson('/api/admin/gym/report/attendance')->assertSuccessful()->json('data');

    expect($data['total_visits'])->toBe(3)
        ->and($data['unique_members'])->toBe(2)
        ->and($data['busiest_hour'])->toBe(7)
        ->and($data['per_day'])->toHaveCount(2)
        ->and($data['most_frequent'][0]['visits'])->toBe(2);
});

it('honours the reporting window', function () {
    $this->memberships->assign(($this->newMember)('This month'), $this->monthly);

    $lastMonth = now()->subMonthNoOverflow();

    expect($this->getJson('/api/admin/gym/report/membership-summary?from='.$lastMonth->startOfMonth()->toDateString().'&to='.$lastMonth->endOfMonth()->toDateString())->json('data.total'))
        ->toBe(0);
});

it('keeps one company\'s numbers out of another\'s', function () {
    $this->memberships->assign(($this->newMember)('Mine'), $this->monthly);

    GymTestSupport::makeGymCompany('Other Gym', 'OTHER');

    expect($this->getJson('/api/admin/gym/report/membership-summary')->json('data.total'))->toBe(0);
});

it('blocks the reports when the gym module is off', function () {
    GymTestSupport::moduleService()->disable($this->company, 'gym');

    $this->getJson('/api/admin/gym/report/revenue-by-plan')
        ->assertForbidden()
        ->assertJsonPath('module', 'gym');
});

it('answers with empty figures rather than failing on a quiet month', function () {
    $data = $this->getJson('/api/admin/gym/report/renewals')->assertSuccessful()->json('data');

    expect($data['terms_sold'])->toBe(0)
        ->and((float) $data['renewal_share'])->toBe(0.0)
        ->and($this->getJson('/api/admin/gym/report/attendance')->json('data.total_visits'))->toBe(0);
});

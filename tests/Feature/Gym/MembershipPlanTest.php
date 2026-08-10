<?php

use App\Models\Product;
use App\Enums\ProductTypeEnum;
use App\Models\MembershipPlan;
use App\Models\ProductVariant;
use App\Enums\DurationUnitEnum;
use Tests\Feature\Gym\GymTestSupport;
use App\Enums\MembershipDurationPresetEnum;
use App\Services\Gym\MembershipPlanService;

/*
| Phase 5 — Membership Plans
| (docs/saas-modular-platform-and-gym-module-plan.md §4.2, §6.1).
|
| Each plan sells through a real service Product, which is what lets a
| membership invoice be an ordinary invoice — no gym-specific accounting.
| The term arithmetic is pinned down here too, because Phase 6's expiry dates
| are computed from it.
*/

beforeEach(function () {
    ['company' => $this->company, 'branch' => $this->branch] = GymTestSupport::makeGymCompany();

    $this->service = app(MembershipPlanService::class);
});

it('creates a plan from a standard preset', function () {
    $response = $this->postJson('/api/admin/gym/membership-plan', [
        'name' => 'Quarterly',
        'preset' => 'quarterly',
        'price' => 5500,
    ])->assertSuccessful();

    expect($response->json('data.duration_unit'))->toBe('month')
        ->and($response->json('data.duration_value'))->toBe(3)
        ->and($response->json('data.preset_label'))->toBe('Quarterly')
        ->and($response->json('data.duration_label'))->toBe('Quarterly');
});

it('supports the four standard terms', function (string $preset, string $unit, int $value) {
    $plan = $this->service->create(['name' => ucfirst($preset), 'preset' => $preset, 'price' => 100]);

    expect($plan->duration_unit->value)->toBe($unit)
        ->and($plan->duration_value)->toBe($value);
})->with([
    'monthly' => ['monthly', 'month', 1],
    'quarterly' => ['quarterly', 'month', 3],
    'half-yearly' => ['half_yearly', 'month', 6],
    'yearly' => ['yearly', 'year', 1],
]);

it('accepts a custom term', function () {
    $plan = $this->service->create([
        'name' => '10-Day Trial',
        'duration_unit' => 'day',
        'duration_value' => 10,
        'price' => 500,
    ]);

    expect($plan->duration_unit)->toBe(DurationUnitEnum::Day)
        ->and($plan->duration_value)->toBe(10)
        ->and($plan->preset)->toBe(MembershipDurationPresetEnum::Custom)
        ->and($plan->duration_label)->toBe('10 Days');
});

it('labels a hand-entered term that matches a standard one', function () {
    $plan = $this->service->create([
        'name' => 'Three Months',
        'duration_unit' => 'month',
        'duration_value' => 3,
        'price' => 5500,
    ]);

    expect($plan->preset)->toBe(MembershipDurationPresetEnum::Quarterly);
});

it('needs a term when no preset is chosen', function () {
    $this->postJson('/api/admin/gym/membership-plan', [
        'name' => 'Vague Plan',
        'price' => 100,
    ])->assertStatus(422)->assertJsonValidationErrors('duration_value');
});

it('creates a service product behind every plan', function () {
    $plan = $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);

    $product = Product::query()->findOrFail($plan->product_id);

    expect($product->product_type)->toBe(ProductTypeEnum::SERVICE)
        ->and($product->name)->toBe('Membership — Monthly')
        ->and($product->is_saleable)->toBeTrue()
        ->and($product->is_purchasable)->toBeFalse()
        ->and(ProductVariant::query()->where('product_id', $product->id)->value('sales_price'))
        ->toBe(2000.0);
});

it('keeps the product in step when the plan price changes', function () {
    $plan = $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);

    $this->putJson("/api/admin/gym/membership-plan/{$plan->id}", [
        'name' => 'Monthly Plus',
        'price' => 2500,
    ])->assertSuccessful();

    $product = Product::query()->findOrFail($plan->fresh()->product_id);

    expect($product->name)->toBe('Membership — Monthly Plus')
        ->and(ProductVariant::query()->where('product_id', $product->id)->value('sales_price'))
        ->toBe(2500.0);
});

it('does not create a second product when a plan is edited', function () {
    $plan = $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);
    $productId = $plan->product_id;

    $this->service->update($plan, ['price' => 3000]);
    $this->service->update($plan->fresh(), ['name' => 'Renamed']);

    expect($plan->fresh()->product_id)->toBe($productId)
        ->and(Product::query()->count())->toBe(1);
});

it('files membership products under their own category', function () {
    $plan = $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);

    expect(Product::query()->findOrFail($plan->product_id)->productCategory->name)->toBe('Memberships');
});

it('numbers plans per company', function () {
    $first = $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 1]);
    $second = $this->service->create(['name' => 'Yearly', 'preset' => 'yearly', 'price' => 2]);

    expect($first->code)->toBe('MPLAN-0001')
        ->and($second->code)->toBe('MPLAN-0002');
});

it('rejects a duplicate plan code', function () {
    $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 1, 'code' => 'MPLAN-0001']);

    $this->postJson('/api/admin/gym/membership-plan', [
        'name' => 'Another',
        'preset' => 'monthly',
        'price' => 1,
        'code' => 'MPLAN-0001',
    ])->assertStatus(422)->assertJsonValidationErrors('code');
});

it('rejects a negative price', function () {
    $this->postJson('/api/admin/gym/membership-plan', [
        'name' => 'Free Money',
        'preset' => 'monthly',
        'price' => -1,
    ])->assertStatus(422)->assertJsonValidationErrors('price');
});

it('deactivates a plan without deleting it', function () {
    $plan = $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);

    $this->putJson("/api/admin/gym/membership-plan/{$plan->id}/toggle-active")->assertSuccessful();

    expect($plan->fresh()->is_active)->toBeFalse()
        ->and(MembershipPlan::query()->count())->toBe(1);
});

it('filters by active state', function () {
    $this->service->create(['name' => 'Active', 'preset' => 'monthly', 'price' => 1]);
    $inactive = $this->service->create(['name' => 'Retired', 'preset' => 'yearly', 'price' => 2]);
    $inactive->update(['is_active' => false]);

    expect($this->getJson('/api/admin/gym/membership-plan?is_active=1')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/admin/gym/membership-plan?is_active=0')->json('data'))->toHaveCount(1);
});

it('computes the last day of a term inclusively', function (string $preset, string $start, string $expectedEnd) {
    $plan = $this->service->create(['name' => 'Term', 'preset' => $preset, 'price' => 1]);

    expect($plan->endDateFrom(Carbon\Carbon::parse($start))->toDateString())->toBe($expectedEnd);
})->with([
    'one month from the 1st' => ['monthly', '2026-01-01', '2026-01-31'],
    'one month from mid-month' => ['monthly', '2026-03-15', '2026-04-14'],
    'three months' => ['quarterly', '2026-01-01', '2026-03-31'],
    'six months' => ['half_yearly', '2026-01-01', '2026-06-30'],
    'one year' => ['yearly', '2026-01-01', '2026-12-31'],
]);

it('does not overflow a month-end start date', function () {
    // 31 January + 1 month must land in February, not spill into March.
    $plan = $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 1]);

    expect($plan->endDateFrom(Carbon\Carbon::parse('2026-01-31'))->toDateString())->toBe('2026-02-27')
        ->and($plan->endDateFrom(Carbon\Carbon::parse('2026-05-31'))->toDateString())->toBe('2026-06-29');
});

it('handles a leap year', function () {
    $yearly = $this->service->create(['name' => 'Yearly', 'preset' => 'yearly', 'price' => 1]);
    $monthly = $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 1]);

    expect($yearly->endDateFrom(Carbon\Carbon::parse('2028-02-29'))->toDateString())->toBe('2029-02-27')
        ->and($monthly->endDateFrom(Carbon\Carbon::parse('2028-01-31'))->toDateString())->toBe('2028-02-28');
});

it('keeps one company\'s plans away from another', function () {
    $this->service->create(['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000]);

    GymTestSupport::makeGymCompany('Other Gym', 'OTHER');

    expect($this->getJson('/api/admin/gym/membership-plan')->json('data'))->toHaveCount(0);
});

it('keeps every plan when the module is switched off and back on', function () {
    $plan = $this->service->create(['name' => 'Founder Special', 'preset' => 'yearly', 'price' => 15000]);

    $modules = GymTestSupport::moduleService();
    $modules->disable($this->company, 'gym');

    // The rows stay exactly where they are while the doors are closed.
    expect(MembershipPlan::withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(1);

    $modules->enable($this->company, 'gym');

    $reloaded = $plan->fresh();

    expect($reloaded->name)->toBe('Founder Special')
        ->and($reloaded->price)->toBe(15000.0)
        ->and($reloaded->code)->toBe($plan->code);
});

it('seeds the four standard plans when the module is first enabled', function () {
    $modules = GymTestSupport::moduleService();
    $modules->disable($this->company, 'gym');
    $modules->enable($this->company, 'gym');

    expect(MembershipPlan::query()->pluck('name')->all())
        ->toEqualCanonicalizing(['Monthly', 'Quarterly', 'Half-Yearly', 'Yearly']);
});

it('does not duplicate the default plans across an off/on cycle', function () {
    $modules = GymTestSupport::moduleService();

    foreach (range(1, 3) as $ignored) {
        $modules->disable($this->company, 'gym');
        $modules->enable($this->company, 'gym');
    }

    expect(MembershipPlan::query()->count())->toBe(4)
        // Each plan keeps exactly one service product behind it.
        ->and(Product::query()->count())->toBe(4);
});

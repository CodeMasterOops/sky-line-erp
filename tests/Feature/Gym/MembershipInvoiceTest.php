<?php

use App\Models\Invoice;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\Membership;
use App\Services\Gym\MemberService;
use Tests\Feature\Gym\GymTestSupport;
use App\Services\Gym\MembershipService;
use App\Services\Gym\MembershipPlanService;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 6 — billing a membership
| (docs/saas-modular-platform-and-gym-module-plan.md §6.5).
|
| The payoff for putting a service Product behind every plan: a membership is
| billed by the ordinary sales invoice path. Same numbering, same tax engine,
| same GL posting, and it lands in AR without a line of gym-specific accounting.
*/

beforeEach(function () {
    ['company' => $this->company, 'branch' => $this->branch] = GymTestSupport::makeGymCompany();

    $this->memberships = app(MembershipService::class);
    $this->member = app(MemberService::class)->create(['name' => 'Ram Bahadur']);
    $this->plan = app(MembershipPlanService::class)->create([
        'name' => 'Monthly',
        'preset' => 'monthly',
        'price' => 2000,
    ]);
});

it('raises an invoice against the member when a term is sold', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);

    $invoice = Invoice::query()->findOrFail($membership->invoice_id);

    expect($invoice->party_id)->toBe($this->member->party_id)
        ->and((float) $invoice->total_amount)->toBe(2000.0)
        ->and($invoice->status)->toBe(StatusEnum::APPROVED);
});

it('points the invoice back at the term it paid for', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);

    $invoice = Invoice::query()->findOrFail($membership->invoice_id);

    expect($invoice->reference_type)->toBe(Membership::class)
        ->and($invoice->reference_id)->toBe($membership->id);
});

it('bills through the plan\'s service product', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);

    $invoice = Invoice::query()->with('invoiceItems.productVariant')->findOrFail($membership->invoice_id);
    $item = $invoice->invoiceItems->first();

    expect($item->productVariant->product_id)->toBe($this->plan->product_id)
        ->and((float) $item->rate)->toBe(2000.0)
        ->and((float) $item->quantity)->toBe(1.0);
});

it('posts the membership to the ledger', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);

    // An approved invoice journals through the normal sales posting path, so
    // membership revenue reaches the books with no gym-specific accounting.
    expect(Journal::query()->where('reference_id', $membership->invoice_id)->exists())->toBeTrue();
});

it('bills the joining fee as its own line', function () {
    $plan = app(MembershipPlanService::class)->create([
        'name' => 'With Joining Fee',
        'preset' => 'monthly',
        'price' => 2000,
        'joining_fee' => 500,
    ]);

    $membership = $this->memberships->assign($this->member, $plan);
    $invoice = Invoice::query()->with('invoiceItems')->findOrFail($membership->invoice_id);

    expect($invoice->invoiceItems)->toHaveCount(2)
        ->and((float) $invoice->total_amount)->toBe(2500.0);
});

it('bills the discounted amount', function () {
    $membership = $this->memberships->assign($this->member, $this->plan, ['discount_amount' => 500]);

    expect((float) Invoice::query()->findOrFail($membership->invoice_id)->total_amount)->toBe(1500.0);
});

it('invoices a renewal too', function () {
    $first = $this->memberships->assign($this->member, $this->plan);
    $renewal = $this->memberships->renew($first);

    expect($renewal->invoice_id)->not->toBeNull()
        ->and($renewal->invoice_id)->not->toBe($first->invoice_id)
        ->and(Invoice::query()->count())->toBe(2);
});

it('skips billing when the company turns it off', function () {
    app(CompanyModuleService::class)->updateSettings($this->company, 'gym', [
        'auto_invoice_on_assignment' => false,
    ]);

    $membership = $this->memberships->assign($this->member, $this->plan);

    expect($membership->invoice_id)->toBeNull()
        ->and(Invoice::query()->count())->toBe(0)
        // The term still stands — it is simply unbilled.
        ->and($membership->status->value)->toBe('active');
});

it('skips billing when the caller asks it to', function () {
    $membership = $this->memberships->assign($this->member, $this->plan, ['create_invoice' => false]);

    expect($membership->invoice_id)->toBeNull();
});

it('leaves the invoice alone when a membership is cancelled', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);
    $invoiceId = $membership->invoice_id;

    $this->memberships->cancel($membership, 'Moved away.');

    // Cancelling a membership is not a credit note: the receivable stands until
    // somebody puts a sales return through the normal flow.
    $invoice = Invoice::query()->findOrFail($invoiceId);

    expect($invoice->status)->toBe(StatusEnum::APPROVED)
        ->and($invoice->voided_at)->toBeNull();
});

it('shows the membership invoice on the member\'s account', function () {
    $membership = $this->memberships->assign($this->member, $this->plan);

    // The member is an ordinary customer, so the invoice reaches them through
    // the party relation the rest of Sales already uses.
    expect($this->member->party->invoices()->pluck('id'))->toContain($membership->invoice_id);
});

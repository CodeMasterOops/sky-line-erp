<?php

namespace App\Services\Gym;

use Carbon\Carbon;
use App\Models\Member;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\Membership;
use Carbon\CarbonInterface;
use App\Models\MembershipPlan;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use App\Enums\MembershipStatusEnum;
use App\Services\Sales\InvoiceService;
use App\Services\DocumentNumberGenerator;
use App\Services\Modules\CompanyModuleService;
use Illuminate\Validation\ValidationException;

/**
 * Assigning, renewing and cancelling membership terms.
 *
 * A term is never edited in place: renewing inserts a new row chained through
 * `renewed_from_id`. That keeps history immutable and keeps each invoice
 * attached to the period it actually paid for.
 */
class MembershipService
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly MemberStatusSynchroniser $memberStatus,
        private readonly CompanyModuleService $modules,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Sell a term to a member.
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(Member $member, MembershipPlan $plan, array $data = []): Membership
    {
        $this->ensureTenantContext($member);
        $this->assertPlanIsSellable($plan);
        $this->assertNoOccupyingMembership($member);

        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])->startOfDay()
            : now()->startOfDay();

        // Selling a term and billing it are one business action: if the invoice
        // cannot be raised (say the ledger is not configured yet), the term must
        // not be left behind unbilled with an error on screen.
        return DB::transaction(function () use ($member, $plan, $startDate, $data): Membership {
            $membership = $this->writeTerm(
                member: $member,
                plan: $plan,
                startDate: $startDate,
                data: $data,
                renewedFrom: null,
            );

            return $this->finalise($member, $membership, $data);
        });
    }

    /**
     * Sell the next term to a member who already has one.
     *
     * Renewing early keeps the terms continuous (the new one starts the day
     * after the old one ends). Renewing after a lapse starts today by default,
     * because a gym does not usually give away the days somebody was not a
     * member — `lapsed_renewal_continues_term` flips that.
     *
     * @param  array<string, mixed>  $data
     */
    public function renew(Membership $membership, array $data = []): Membership
    {
        $member = $membership->member;
        $this->ensureTenantContext($member);
        $plan = isset($data['membership_plan_id'])
            ? $this->resolvePlan((int) $data['membership_plan_id'])
            : $membership->membershipPlan;

        $this->assertPlanIsSellable($plan);

        if ($membership->status === MembershipStatusEnum::Cancelled) {
            throw ValidationException::withMessages([
                'membership' => ['A cancelled membership cannot be renewed. Assign a new one instead.'],
            ]);
        }

        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])->startOfDay()
            : $this->nextTermStart($membership);

        return DB::transaction(function () use ($member, $plan, $startDate, $data, $membership): Membership {
            // The term being renewed is closed out first, so the member never
            // holds two occupying terms at once.
            if ($membership->isCurrent()) {
                $membership->update([
                    'status' => MembershipStatusEnum::Expired,
                    'expired_at' => now(),
                ]);
            }

            $renewal = $this->writeTerm(
                member: $member,
                plan: $plan,
                startDate: $startDate,
                data: $data,
                renewedFrom: $membership,
            );

            return $this->finalise($member, $renewal, $data);
        });
    }

    public function cancel(Membership $membership, ?string $reason = null): Membership
    {
        if ($membership->status === MembershipStatusEnum::Cancelled) {
            return $membership;
        }

        $membership->update([
            'status' => MembershipStatusEnum::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        // The invoice raised for the term is deliberately left alone: cancelling
        // a membership is not a credit note. Refunds go through the normal sales
        // return flow so the ledger stays the single source of truth.
        $this->memberStatus->sync($membership->member);

        return $membership->fresh();
    }

    /**
     * The day the next term should start after the given one.
     */
    public function nextTermStart(Membership $membership): CarbonInterface
    {
        $dayAfter = $membership->end_date->copy()->addDay()->startOfDay();

        if ($dayAfter->isFuture() || $dayAfter->isToday()) {
            return $dayAfter;
        }

        $continues = (bool) ($this->settings($membership->company_id)['lapsed_renewal_continues_term'] ?? false);

        return $continues ? $dayAfter : now()->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeTerm(
        Member $member,
        MembershipPlan $plan,
        CarbonInterface $startDate,
        array $data,
        ?Membership $renewedFrom,
    ): Membership {
        $price = (float) ($data['price'] ?? $plan->price);
        $discount = (float) ($data['discount_amount'] ?? 0);
        // The joining fee is a one-off: charged on a member's first ever term,
        // never on a renewal.
        $joiningFee = $renewedFrom === null && ! $this->hasPriorTerm($member)
            ? (float) ($data['joining_fee'] ?? $plan->joining_fee)
            : 0.0;

        return Membership::create([
            // Set explicitly rather than left to the MultiTenant/BranchTenant
            // creating hooks: raising an invoice dispatches the IRD sync job,
            // which resets the ambient tenant context when the queue runs
            // synchronously. A term belongs to its member's company and branch
            // regardless of what the context happens to hold.
            'company_id' => $member->company_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'membership_no' => $this->nextNumber((int) $member->company_id),
            'start_date' => $startDate->toDateString(),
            'end_date' => $plan->endDateFrom($startDate)->toDateString(),
            'status' => MembershipStatusEnum::Active,
            'price' => $price,
            'discount_amount' => $discount,
            'joining_fee' => $joiningFee,
            'payable_amount' => max(0, $price - $discount + $joiningFee),
            'renewed_from_id' => $renewedFrom?->id,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => auth('admin')->id(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function finalise(Member $member, Membership $membership, array $data): Membership
    {
        $shouldInvoice = array_key_exists('create_invoice', $data)
            ? (bool) $data['create_invoice']
            : (bool) ($this->settings($member->company_id)['auto_invoice_on_assignment'] ?? true);

        if ($shouldInvoice) {
            $this->raiseInvoice($member, $membership);
        }

        $this->memberStatus->sync($member);

        return $membership->fresh(['membershipPlan', 'invoice', 'member.party']);
    }

    /**
     * Bill the term through the plan's service product, so this is an ordinary
     * sales invoice: same numbering, same tax engine, same GL posting, and it
     * shows up in AR ageing and statements without any gym-specific code.
     */
    private function raiseInvoice(Member $member, Membership $membership): ?Invoice
    {
        $variant = $this->defaultVariant($membership->membershipPlan);

        if (! $variant || ! auth('admin')->check()) {
            // No sellable item, or no acting user to attribute the invoice to
            // (a queued context). The term still stands; it is simply unbilled.
            return null;
        }

        $items = [[
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'rate' => max(0, $membership->price - $membership->discount_amount),
        ]];

        if ($membership->joining_fee > 0) {
            $items[] = [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'rate' => $membership->joining_fee,
            ];
        }

        // Approving an invoice dispatches the IRD sync job, which resets the
        // ambient tenant context in its finally block. On a synchronous queue
        // that happens inline, so anything the caller does afterwards — a
        // renewal in the same request, a second term — would run without a
        // tenant. Capture and restore around the call.
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        try {
            $invoice = $this->invoices->createInvoice([
                'party_id' => $member->party_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'status' => StatusEnum::APPROVED->value,
                'reference_type' => Membership::class,
                'reference_id' => $membership->id,
                'remarks' => 'Membership '.$membership->membership_no.' — '.$membership->membershipPlan->name,
                'items' => $items,
            ]);
        } finally {
            TenantService::setCompanyId($companyId ?? $member->company_id);
            TenantService::setBranchId($branchId ?? $member->branch_id);
        }

        $membership->update(['invoice_id' => $invoice->id]);

        return $invoice;
    }

    private function defaultVariant(MembershipPlan $plan): ?ProductVariant
    {
        if (! $plan->product_id) {
            return null;
        }

        return ProductVariant::query()
            ->where('product_id', $plan->product_id)
            ->orderByDesc('is_default')
            ->first();
    }

    /**
     * Make sure the tenant context matches the member being worked on.
     *
     * Needed because approving an invoice dispatches the IRD sync job, and that
     * job resets the ambient context in its finally block — inline, when the
     * queue runs synchronously, and after commit, so the damage lands on
     * whatever the caller does next. Establishing context here means each
     * operation stands on its own rather than trusting what came before.
     */
    private function ensureTenantContext(Member $member): void
    {
        if (TenantService::companyId() === null) {
            TenantService::setCompanyId($member->company_id);
        }

        if (TenantService::branchId() === null) {
            TenantService::setBranchId($member->branch_id);
        }
    }

    private function assertPlanIsSellable(MembershipPlan $plan): void
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'membership_plan_id' => ['That membership plan is not active.'],
            ]);
        }
    }

    private function assertNoOccupyingMembership(Member $member): void
    {
        $allowMultiple = (bool) ($this->settings($member->company_id)['allow_multiple_active_memberships'] ?? false);

        if ($allowMultiple) {
            return;
        }

        $existing = Membership::query()
            ->where('member_id', $member->id)
            ->current()
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'member_id' => ['This member already has a running membership. Renew it instead of assigning a second one.'],
            ]);
        }
    }

    private function hasPriorTerm(Member $member): bool
    {
        return Membership::query()->where('member_id', $member->id)->exists();
    }

    private function resolvePlan(int $planId): MembershipPlan
    {
        $plan = MembershipPlan::query()->find($planId);

        if (! $plan) {
            throw ValidationException::withMessages([
                'membership_plan_id' => ['That membership plan does not exist.'],
            ]);
        }

        return $plan;
    }

    private function nextNumber(int $companyId): string
    {
        $generate = fn (): string => $this->numbers->companyPadded(Membership::class, 'MSHIP-', $companyId, 5);

        return DB::transactionLevel() > 0 ? $generate() : DB::transaction($generate);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(?int $companyId): array
    {
        $companyId ??= TenantService::companyId();

        return $companyId ? $this->modules->settingsFor((int) $companyId, 'gym') : [];
    }
}

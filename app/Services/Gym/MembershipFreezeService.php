<?php

namespace App\Services\Gym;

use Carbon\Carbon;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use App\Enums\MembershipStatusEnum;
use Illuminate\Validation\ValidationException;

/**
 * Pausing and resuming a membership.
 *
 * Resuming extends the term by exactly the days lost, so a member who freezes
 * for a fortnight gets that fortnight back rather than paying for time they
 * could not use. The plan's `max_freeze_days` caps the total across the term.
 */
class MembershipFreezeService
{
    public function __construct(private readonly MemberStatusSynchroniser $memberStatus) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function freeze(Membership $membership, array $data = []): Membership
    {
        if ($membership->status !== MembershipStatusEnum::Active) {
            throw ValidationException::withMessages([
                'membership' => ['Only an active membership can be frozen.'],
            ]);
        }

        $allowance = (int) ($membership->membershipPlan?->max_freeze_days ?? 0);

        if ($allowance <= 0) {
            throw ValidationException::withMessages([
                'membership' => ['This plan does not allow freezing.'],
            ]);
        }

        if ($membership->freeze_days_used >= $allowance) {
            throw ValidationException::withMessages([
                'membership' => ["This membership has used its full freeze allowance of {$allowance} day(s)."],
            ]);
        }

        $fromDate = isset($data['from_date']) ? Carbon::parse($data['from_date']) : now();

        if ($fromDate->gt($membership->end_date)) {
            throw ValidationException::withMessages([
                'from_date' => ['The freeze cannot start after the membership ends.'],
            ]);
        }

        DB::transaction(function () use ($membership, $fromDate, $data): void {
            $membership->freezes()->create([
                'from_date' => $fromDate->toDateString(),
                'reason' => $data['reason'] ?? null,
                'created_by_id' => auth('admin')->id(),
            ]);

            $membership->update(['status' => MembershipStatusEnum::Frozen]);
        });

        $this->memberStatus->sync($membership->member);

        return $membership->fresh(['freezes', 'membershipPlan']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resume(Membership $membership, array $data = []): Membership
    {
        if ($membership->status !== MembershipStatusEnum::Frozen) {
            throw ValidationException::withMessages([
                'membership' => ['This membership is not frozen.'],
            ]);
        }

        $freeze = $membership->runningFreeze();

        if (! $freeze) {
            throw ValidationException::withMessages([
                'membership' => ['No running freeze was found for this membership.'],
            ]);
        }

        $toDate = isset($data['to_date']) ? Carbon::parse($data['to_date']) : now();

        if ($toDate->lt($freeze->from_date)) {
            throw ValidationException::withMessages([
                'to_date' => ['The resume date cannot be before the freeze started.'],
            ]);
        }

        // Inclusive of both ends: freezing and resuming on the same day costs
        // the member one day and gives one day back.
        $days = (int) $freeze->from_date->diffInDays($toDate) + 1;
        $remaining = max(0, (int) ($membership->membershipPlan?->max_freeze_days ?? 0) - $membership->freeze_days_used);
        $days = min($days, $remaining);

        DB::transaction(function () use ($membership, $freeze, $toDate, $days): void {
            $freeze->update([
                'to_date' => $toDate->toDateString(),
                'days' => $days,
            ]);

            $membership->update([
                'status' => MembershipStatusEnum::Active,
                // The term is pushed out by the days lost.
                'end_date' => $membership->end_date->copy()->addDays($days)->toDateString(),
                'freeze_days_used' => $membership->freeze_days_used + $days,
            ]);
        });

        $this->memberStatus->sync($membership->member);

        return $membership->fresh(['freezes', 'membershipPlan']);
    }
}

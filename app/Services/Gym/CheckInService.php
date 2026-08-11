<?php

namespace App\Services\Gym;

use App\Models\Member;
use App\Models\Membership;
use App\Models\MemberCheckIn;
use App\Enums\CheckInMethodEnum;
use Illuminate\Validation\ValidationException;

/**
 * Recording visits.
 *
 * A member without a running term is still let through and recorded — turning
 * people away at the door is a policy decision for the gym, not the software —
 * but the visit is flagged so the front desk can ask about a renewal.
 */
class CheckInService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function checkIn(Member $member, array $data = []): MemberCheckIn
    {
        // Only today's open visit blocks a new one. Somebody who forgot to
        // check out last Tuesday should not be locked out for good; that stale
        // row simply stays open as a record of what happened.
        $open = MemberCheckIn::query()
            ->where('member_id', $member->id)
            ->open()
            ->onDate(now()->toDateString())
            ->first();

        if ($open) {
            throw ValidationException::withMessages([
                'member_id' => ['This member is already checked in. Check them out first.'],
            ]);
        }

        return MemberCheckIn::create([
            'company_id' => $member->company_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->id,
            'membership_id' => $this->currentMembershipId($member),
            'checked_in_at' => $data['checked_in_at'] ?? now(),
            'method' => $data['method'] ?? CheckInMethodEnum::Manual,
            'device_ref' => $data['device_ref'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => auth('admin')->id(),
        ]);
    }

    public function checkOut(MemberCheckIn $checkIn, ?string $at = null): MemberCheckIn
    {
        if ($checkIn->checked_out_at) {
            throw ValidationException::withMessages([
                'check_in' => ['This visit has already been checked out.'],
            ]);
        }

        $checkedOutAt = $at ? \Carbon\Carbon::parse($at) : now();

        if ($checkedOutAt->lt($checkIn->checked_in_at)) {
            throw ValidationException::withMessages([
                'checked_out_at' => ['Check-out cannot be before check-in.'],
            ]);
        }

        $checkIn->update(['checked_out_at' => $checkedOutAt]);

        return $checkIn->fresh();
    }

    /**
     * Find a member by their member ID or phone — what the front desk types.
     */
    public function findMember(string $identifier): ?Member
    {
        return Member::query()
            ->with(['party', 'currentMembership.membershipPlan'])
            ->where('member_code', $identifier)
            ->orWhereHas('party', fn ($q) => $q->where('phone', $identifier))
            ->first();
    }

    private function currentMembershipId(Member $member): ?int
    {
        return Membership::query()
            ->where('member_id', $member->id)
            ->current()
            ->orderByDesc('start_date')
            ->value('id');
    }
}

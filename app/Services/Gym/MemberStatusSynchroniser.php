<?php

namespace App\Services\Gym;

use App\Models\Member;
use App\Models\Membership;
use App\Enums\MemberStatusEnum;
use App\Enums\MembershipStatusEnum;

/**
 * Keeps `members.status` in step with the member's latest term.
 *
 * The column is denormalised so member lists can filter cheaply; it is derived
 * here and never edited by hand, which is why nothing else in the module writes
 * to it.
 */
class MemberStatusSynchroniser
{
    public function sync(Member $member): MemberStatusEnum
    {
        $status = $this->resolve($member);

        if ($member->status !== $status) {
            $member->update(['status' => $status]);
        }

        return $status;
    }

    private function resolve(Member $member): MemberStatusEnum
    {
        $latest = Membership::query()
            ->withoutGlobalScopes()
            ->where('company_id', $member->company_id)
            ->where('member_id', $member->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            // Registered but never sold a term.
            return MemberStatusEnum::Inactive;
        }

        return match ($latest->status) {
            MembershipStatusEnum::Active, MembershipStatusEnum::Pending => MemberStatusEnum::Active,
            MembershipStatusEnum::Frozen => MemberStatusEnum::Frozen,
            MembershipStatusEnum::Expired => MemberStatusEnum::Expired,
            MembershipStatusEnum::Cancelled => MemberStatusEnum::Cancelled,
        };
    }
}

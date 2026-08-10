<?php

namespace App\Http\Controllers\Api\Admin\Gym;

use App\Models\Member;
use App\Models\Membership;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Enums\MemberStatusEnum;
use App\Enums\MembershipStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Gym\MembershipResource;

/**
 * The numbers a gym looks at first thing: who is active, who lapses this week,
 * and what the memberships sold recently are worth.
 */
class GymDashboardController extends Controller
{
    #[Permissions('list_member', group: 'gym_member', desc: 'List Members')]
    public function __invoke(Request $request)
    {
        $days = (int) $request->query('days', 7);
        $expiringBy = now()->addDays($days)->toDateString();

        return response()->json([
            'data' => [
                'members' => [
                    'total' => Member::query()->count(),
                    'active' => Member::query()->where('status', MemberStatusEnum::Active->value)->count(),
                    'expired' => Member::query()->where('status', MemberStatusEnum::Expired->value)->count(),
                    'joined_this_month' => Member::query()
                        ->whereDate('joined_on', '>=', now()->startOfMonth()->toDateString())
                        ->count(),
                ],
                'memberships' => [
                    'active' => Membership::query()->active()->count(),
                    'expiring_soon' => Membership::query()->expiringBy($expiringBy)->count(),
                    'expired' => Membership::query()
                        ->where('status', MembershipStatusEnum::Expired->value)
                        ->count(),
                    'sold_this_month' => Membership::query()
                        ->whereDate('start_date', '>=', now()->startOfMonth()->toDateString())
                        ->count(),
                    'revenue_this_month' => (float) Membership::query()
                        ->whereDate('start_date', '>=', now()->startOfMonth()->toDateString())
                        ->sum('payable_amount'),
                ],
                'expiring' => MembershipResource::collection(
                    Membership::query()
                        ->with(['member.party', 'membershipPlan'])
                        ->expiringBy($expiringBy)
                        ->orderBy('end_date')
                        ->limit(10)
                        ->get()
                ),
            ],
        ]);
    }
}

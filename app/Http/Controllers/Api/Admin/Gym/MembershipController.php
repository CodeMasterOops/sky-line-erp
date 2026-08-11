<?php

namespace App\Http\Controllers\Api\Admin\Gym;

use App\Models\Member;
use App\Models\Membership;
use Illuminate\Http\Request;
use App\Models\MembershipPlan;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Gym\MembershipService;
use App\Services\Gym\MembershipFreezeService;
use App\Http\Resources\Admin\Gym\MembershipResource;
use App\Http\Requests\Api\Admin\Gym\RenewMembershipRequest;
use App\Http\Requests\Api\Admin\Gym\AssignMembershipRequest;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    #[Permissions('list_membership', group: 'gym_membership', desc: 'List Memberships')]
    public function index(Request $request)
    {
        $memberships = Membership::query()
            ->with(['member.party', 'membershipPlan', 'invoice'])
            ->filter($request->query())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($request->query('limit', 25));

        return MembershipResource::collection($memberships);
    }

    /**
     * Terms expiring within `days` (7 by default) — the follow-up list a gym
     * works from.
     */
    #[Permissions('list_membership', group: 'gym_membership', desc: 'List Memberships')]
    public function expiring(Request $request)
    {
        $days = (int) $request->query('days', 7);

        $memberships = Membership::query()
            ->with(['member.party', 'membershipPlan'])
            ->expiringBy(now()->addDays($days)->toDateString())
            ->orderBy('end_date')
            ->paginate($request->query('limit', 25));

        return MembershipResource::collection($memberships);
    }

    #[Permissions('assign_membership', group: 'gym_membership', desc: 'Assign Membership')]
    public function store(AssignMembershipRequest $request)
    {
        $data = $request->validated();

        $membership = $this->memberships->assign(
            Member::query()->findOrFail($data['member_id']),
            MembershipPlan::query()->findOrFail($data['membership_plan_id']),
            $data,
        );

        return response()->json([
            'data' => MembershipResource::make($membership->load(['member.party', 'membershipPlan', 'invoice'])),
            'message' => 'Membership assigned successfully.',
        ], 201);
    }

    #[Permissions('list_membership', group: 'gym_membership', desc: 'List Memberships')]
    public function show(Membership $membership)
    {
        return MembershipResource::make($membership->load(['member.party', 'membershipPlan', 'invoice']));
    }

    #[Permissions('renew_membership', group: 'gym_membership', desc: 'Renew Membership')]
    public function renew(RenewMembershipRequest $request, Membership $membership)
    {
        $renewal = $this->memberships->renew($membership, $request->validated());

        return response()->json([
            'data' => MembershipResource::make($renewal->load(['member.party', 'membershipPlan', 'invoice'])),
            'message' => 'Membership renewed successfully.',
        ], 201);
    }

    #[Permissions('cancel_membership', group: 'gym_membership', desc: 'Cancel Membership')]
    public function cancel(Request $request, Membership $membership)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $cancelled = $this->memberships->cancel($membership, $request->input('reason'));

        return response()->json([
            'data' => MembershipResource::make($cancelled->load(['member.party', 'membershipPlan'])),
            'message' => 'Membership cancelled.',
        ]);
    }

    #[Permissions('freeze_membership', group: 'gym_membership', desc: 'Freeze Membership')]
    public function freeze(Request $request, Membership $membership)
    {
        $data = $request->validate([
            'from_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $frozen = app(MembershipFreezeService::class)->freeze($membership, $data);

        return response()->json([
            'data' => MembershipResource::make($frozen->load(['member.party', 'membershipPlan'])),
            'message' => 'Membership frozen.',
        ]);
    }

    #[Permissions('freeze_membership', group: 'gym_membership', desc: 'Freeze Membership')]
    public function resume(Request $request, Membership $membership)
    {
        $data = $request->validate(['to_date' => ['nullable', 'date']]);

        $resumed = app(MembershipFreezeService::class)->resume($membership, $data);

        return response()->json([
            'data' => MembershipResource::make($resumed->load(['member.party', 'membershipPlan'])),
            'message' => 'Membership resumed — the term has been extended by the days lost.',
        ]);
    }

    /**
     * Every term this member has held, newest first.
     */
    #[Permissions('show_member', group: 'gym_member', desc: 'View Member')]
    public function forMember(Member $member)
    {
        return MembershipResource::collection(
            $member->memberships()->with(['membershipPlan', 'invoice'])->get()
        );
    }
}

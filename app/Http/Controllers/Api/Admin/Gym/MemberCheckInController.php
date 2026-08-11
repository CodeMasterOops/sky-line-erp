<?php

namespace App\Http\Controllers\Api\Admin\Gym;

use App\Models\Member;
use Illuminate\Http\Request;
use App\Models\MemberCheckIn;
use App\Annotation\Permissions;
use Illuminate\Validation\Rule;
use App\Enums\CheckInMethodEnum;
use App\Http\Controllers\Controller;
use App\Services\Gym\CheckInService;
use App\Http\Resources\Admin\Gym\MemberResource;
use App\Http\Resources\Admin\Gym\MemberCheckInResource;

class MemberCheckInController extends Controller
{
    public function __construct(private readonly CheckInService $checkIns) {}

    #[Permissions('member_check_in', group: 'gym_check_in', desc: 'Member Check-in')]
    public function index(Request $request)
    {
        $checkIns = MemberCheckIn::query()
            ->with('member.party')
            ->filter($request->query())
            ->orderByDesc('checked_in_at')
            ->paginate($request->query('limit', 25));

        return MemberCheckInResource::collection($checkIns);
    }

    /**
     * Look a member up by member ID or phone — what the front desk types.
     */
    #[Permissions('member_check_in', group: 'gym_check_in', desc: 'Member Check-in')]
    public function lookup(Request $request)
    {
        $request->validate(['identifier' => ['required', 'string', 'max:50']]);

        $member = $this->checkIns->findMember(trim($request->string('identifier')));

        if (! $member) {
            return response()->json(['message' => 'No member found with that ID or phone number.'], 404);
        }

        $current = $member->currentMembership;
        $openCheckIn = MemberCheckIn::query()->where('member_id', $member->id)->open()->first();

        return response()->json([
            'data' => [
                'member' => MemberResource::make($member),
                'membership' => $current ? [
                    'id' => $current->id,
                    'plan_name' => $current->membershipPlan?->name,
                    'end_date' => $current->end_date?->toDateString(),
                    'days_remaining' => $current->daysRemaining(),
                    'status' => $current->status?->value,
                ] : null,
                // `make(null)` would blow up inside the resource, so resolve it
                // to a plain null when the member is not currently inside.
                'open_check_in' => $openCheckIn ? MemberCheckInResource::make($openCheckIn) : null,
            ],
        ]);
    }

    #[Permissions('member_check_in', group: 'gym_check_in', desc: 'Member Check-in')]
    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', \App\Tenancy\TRule::exists('members', 'id')],
            'method' => ['nullable', Rule::enum(CheckInMethodEnum::class)],
            'device_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $checkIn = $this->checkIns->checkIn(
            Member::query()->findOrFail($data['member_id']),
            $data,
        );

        return response()->json([
            'data' => MemberCheckInResource::make($checkIn->load('member.party')),
            'message' => 'Checked in.',
        ], 201);
    }

    #[Permissions('member_check_in', group: 'gym_check_in', desc: 'Member Check-in')]
    public function checkOut(Request $request, MemberCheckIn $checkIn)
    {
        $request->validate(['checked_out_at' => ['nullable', 'date']]);

        $updated = $this->checkIns->checkOut($checkIn, $request->input('checked_out_at'));

        return response()->json([
            'data' => MemberCheckInResource::make($updated->load('member.party')),
            'message' => 'Checked out.',
        ]);
    }
}

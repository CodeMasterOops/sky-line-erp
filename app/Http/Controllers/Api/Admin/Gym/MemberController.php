<?php

namespace App\Http\Controllers\Api\Admin\Gym;

use App\Models\Member;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Services\Gym\MemberService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Gym\MemberResource;
use App\Http\Requests\Api\Admin\Gym\MemberRequest;

class MemberController extends Controller
{
    public function __construct(private readonly MemberService $members) {}

    #[Permissions('list_member', group: 'gym_member', desc: 'List Members')]
    public function index(Request $request)
    {
        $members = Member::query()
            ->with(['party', 'trainer'])
            ->filter($request->query())
            ->latest('id')
            ->paginate($request->query('limit', 25));

        return MemberResource::collection($members);
    }

    #[Permissions('create_member', group: 'gym_member', desc: 'Create Member')]
    public function nextCode()
    {
        return response()->json([
            'data' => ['member_code' => $this->members->nextCode()],
        ]);
    }

    #[Permissions('create_member', group: 'gym_member', desc: 'Create Member')]
    public function store(MemberRequest $request)
    {
        $member = $this->members->create(
            $request->validated(),
            auth('admin')->id(),
        );

        return response()->json([
            'data' => MemberResource::make($member->load(['party', 'trainer'])),
            'message' => 'Member registered successfully.',
        ], 201);
    }

    #[Permissions('show_member', group: 'gym_member', desc: 'View Member')]
    public function show(Member $member)
    {
        return MemberResource::make($member->load(['party', 'trainer', 'referredBy.party']));
    }

    #[Permissions('edit_member', group: 'gym_member', desc: 'Edit Member')]
    public function update(MemberRequest $request, Member $member)
    {
        $member = $this->members->update($member, $request->validated());

        return response()->json([
            'data' => MemberResource::make($member->load(['party', 'trainer'])),
            'message' => 'Member updated successfully.',
        ]);
    }

    #[Permissions('edit_member', group: 'gym_member', desc: 'Edit Member')]
    public function updatePhoto(Request $request, Member $member)
    {
        $request->validate(['photo' => ['required', 'image', 'max:4096']]);

        $member->update(['photo' => $request->file('photo')]);

        return response()->json([
            'data' => MemberResource::make($member->fresh(['party'])),
            'message' => 'Photo updated successfully.',
        ]);
    }

    #[Permissions('delete_member', group: 'gym_member', desc: 'Delete Member')]
    public function destroy(Member $member)
    {
        $this->members->delete($member);

        return response()->json([
            'message' => 'Member deleted successfully.',
        ]);
    }
}

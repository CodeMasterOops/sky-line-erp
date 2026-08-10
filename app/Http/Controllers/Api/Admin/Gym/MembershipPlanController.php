<?php

namespace App\Http\Controllers\Api\Admin\Gym;

use Illuminate\Http\Request;
use App\Models\MembershipPlan;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Gym\MembershipPlanService;
use App\Http\Resources\Admin\Gym\MembershipPlanResource;
use App\Http\Requests\Api\Admin\Gym\MembershipPlanRequest;

class MembershipPlanController extends Controller
{
    public function __construct(private readonly MembershipPlanService $plans) {}

    #[Permissions('list_membership_plan', group: 'gym_membership_plan', desc: 'List Membership Plans')]
    public function index(Request $request)
    {
        $plans = MembershipPlan::query()
            ->with('product')
            ->filter($request->query())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->query('limit', 25));

        return MembershipPlanResource::collection($plans);
    }

    #[Permissions('create_membership_plan', group: 'gym_membership_plan', desc: 'Create Membership Plan')]
    public function store(MembershipPlanRequest $request)
    {
        $plan = $this->plans->create($request->validated());

        return response()->json([
            'data' => MembershipPlanResource::make($plan),
            'message' => 'Membership plan created successfully.',
        ], 201);
    }

    #[Permissions('list_membership_plan', group: 'gym_membership_plan', desc: 'List Membership Plans')]
    public function show(MembershipPlan $membershipPlan)
    {
        return MembershipPlanResource::make($membershipPlan->load('product'));
    }

    #[Permissions('edit_membership_plan', group: 'gym_membership_plan', desc: 'Edit Membership Plan')]
    public function update(MembershipPlanRequest $request, MembershipPlan $membershipPlan)
    {
        $plan = $this->plans->update($membershipPlan, $request->validated());

        return response()->json([
            'data' => MembershipPlanResource::make($plan),
            'message' => 'Membership plan updated successfully.',
        ]);
    }

    #[Permissions('edit_membership_plan', group: 'gym_membership_plan', desc: 'Edit Membership Plan')]
    public function toggleActive(MembershipPlan $membershipPlan)
    {
        $membershipPlan->update(['is_active' => ! $membershipPlan->is_active]);

        return response()->json([
            'data' => MembershipPlanResource::make($membershipPlan->fresh('product')),
            'message' => $membershipPlan->is_active
                ? 'Membership plan activated.'
                : 'Membership plan deactivated.',
        ]);
    }

    #[Permissions('delete_membership_plan', group: 'gym_membership_plan', desc: 'Delete Membership Plan')]
    public function destroy(MembershipPlan $membershipPlan)
    {
        $this->plans->delete($membershipPlan);

        return response()->json([
            'message' => 'Membership plan deleted successfully.',
        ]);
    }
}

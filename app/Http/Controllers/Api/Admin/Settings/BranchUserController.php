<?php

namespace App\Http\Controllers\Api\Admin\Settings;

use App\Models\User;
use App\Models\Branch;
use App\Models\BranchUser;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\Admin\Settings\BranchUserResource;
use App\Http\Requests\Api\Admin\Settings\AssignBranchUserRequest;

class BranchUserController extends Controller
{
    public function index(Branch $branch): JsonResponse
    {
        Gate::authorize('assignUsers', $branch);

        $assignments = BranchUser::with(['user', 'role'])
            ->where('branch_id', $branch->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => BranchUserResource::collection($assignments),
        ]);
    }

    public function store(AssignBranchUserRequest $request, Branch $branch): JsonResponse
    {
        Gate::authorize('assignUsers', $branch);

        $data = $request->validated();

        $existing = BranchUser::where('branch_id', $branch->id)
            ->where('user_id', $data['user_id'])
            ->first();

        if ($existing) {
            if ($existing->is_active) {
                return response()->json([
                    'message' => 'User is already assigned to this branch.',
                ], 422);
            }

            $existing->update([
                'role_id' => $data['role_id'] ?? $existing->role_id,
                'is_active' => true,
            ]);
            $existing->load(['user', 'role']);

            return response()->json([
                'data' => BranchUserResource::make($existing),
                'message' => 'User assigned to branch successfully.',
            ], 201);
        }

        $assignment = BranchUser::create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'user_id' => $data['user_id'],
            'role_id' => $data['role_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $assignment->load(['user', 'role']);

        return response()->json([
            'data' => BranchUserResource::make($assignment),
            'message' => 'User assigned to branch successfully.',
        ], 201);
    }

    public function update(AssignBranchUserRequest $request, Branch $branch, User $user): JsonResponse
    {
        Gate::authorize('assignUsers', $branch);

        $assignment = BranchUser::where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $assignment->update($request->only(['role_id', 'is_active']));
        $assignment->load(['user', 'role']);

        return response()->json([
            'data' => BranchUserResource::make($assignment),
            'message' => 'Branch assignment updated successfully.',
        ]);
    }

    public function destroy(Branch $branch, User $user): JsonResponse
    {
        Gate::authorize('assignUsers', $branch);

        $assignment = BranchUser::where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $assignment->delete();

        return response()->json([
            'message' => 'User removed from branch successfully.',
        ]);
    }
}

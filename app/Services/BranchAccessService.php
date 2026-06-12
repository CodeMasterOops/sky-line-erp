<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Auth\Access\AuthorizationException;

class BranchAccessService
{
    /**
     * Determine if the given user may access the given branch.
     * Admins always pass; regular users must have an active branch_users record.
     */
    public function canUserAccessBranch(User $user, int $branchId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->branchAssignments()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Return all branches the user is permitted to access, ordered by head-office first.
     */
    public function getAccessibleBranches(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Branch::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderByDesc('is_head_office')
                ->orderBy('name')
                ->get();
        }

        return $user->branches()
            ->where('branches.is_active', true)
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve the effective role for a user in a specific branch.
     * Falls back to the user's first company-level role when no branch role is set.
     */
    public function getEffectiveRole(User $user, int $branchId): ?Role
    {
        $assignment = $user->branchAssignments()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if ($assignment && $assignment->role_id) {
            return $assignment->role;
        }

        return $user->roles()->first();
    }

    /**
     * Return the resolved permission list for a user in a specific branch.
     * Admins receive a wildcard marker so hasPermission() grants everything.
     */
    public function getEffectivePermissions(User $user, int $branchId): array
    {
        if ($user->isAdmin()) {
            return ['*'];
        }

        $role = $this->getEffectiveRole($user, $branchId);

        return $role?->permissions ?? [];
    }

    /**
     * Assert the user can access the branch or throw an authorization exception.
     */
    public function assertUserCanAccessBranch(User $user, int $branchId): void
    {
        if (! $this->canUserAccessBranch($user, $branchId)) {
            throw new AuthorizationException('You do not have access to this branch.');
        }
    }
}

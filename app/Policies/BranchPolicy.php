<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Branch;
use App\Services\BranchAccessService;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Branch $branch): bool
    {
        return app(BranchAccessService::class)->canUserAccessBranch($user, $branch->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->isAdmin();
    }

    public function assignUsers(User $user, Branch $branch): bool
    {
        return $user->isAdmin();
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends OwnedByCompanyPolicy
{
    public function view(User $authUser, User $user): bool
    {
        return $this->belongsToSameCompany($authUser, $user);
    }

    public function update(User $authUser, User $user): bool
    {
        return $this->belongsToSameCompany($authUser, $user);
    }

    public function delete(User $authUser, User $user): bool
    {
        return $this->belongsToSameCompany($authUser, $user);
    }

    public function updateStatus(User $authUser, User $user): bool
    {
        return $this->belongsToSameCompany($authUser, $user);
    }
}

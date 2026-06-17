<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Party;

class PartyPolicy extends OwnedByCompanyPolicy
{
    public function view(User $authUser, Party $party): bool
    {
        return $this->belongsToSameCompany($authUser, $party);
    }

    public function update(User $authUser, Party $party): bool
    {
        return $this->belongsToSameCompany($authUser, $party);
    }

    public function delete(User $authUser, Party $party): bool
    {
        return $this->belongsToSameCompany($authUser, $party);
    }
}

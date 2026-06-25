<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Journal;

class JournalPolicy extends OwnedByCompanyPolicy
{
    public function view(User $authUser, Journal $journal): bool
    {
        return $this->belongsToSameCompany($authUser, $journal);
    }

    public function update(User $authUser, Journal $journal): bool
    {
        return $this->belongsToSameCompany($authUser, $journal);
    }

    public function delete(User $authUser, Journal $journal): bool
    {
        return $this->belongsToSameCompany($authUser, $journal);
    }
}

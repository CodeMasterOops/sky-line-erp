<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;

class BillPolicy extends OwnedByCompanyPolicy
{
    public function view(User $authUser, Bill $bill): bool
    {
        return $this->belongsToSameCompany($authUser, $bill);
    }

    public function update(User $authUser, Bill $bill): bool
    {
        return $this->belongsToSameCompany($authUser, $bill);
    }

    public function delete(User $authUser, Bill $bill): bool
    {
        return $this->belongsToSameCompany($authUser, $bill);
    }
}

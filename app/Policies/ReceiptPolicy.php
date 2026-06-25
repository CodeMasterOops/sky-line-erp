<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Receipt;

class ReceiptPolicy extends OwnedByCompanyPolicy
{
    public function view(User $authUser, Receipt $receipt): bool
    {
        return $this->belongsToSameCompany($authUser, $receipt);
    }

    public function update(User $authUser, Receipt $receipt): bool
    {
        return $this->belongsToSameCompany($authUser, $receipt);
    }

    public function delete(User $authUser, Receipt $receipt): bool
    {
        return $this->belongsToSameCompany($authUser, $receipt);
    }
}

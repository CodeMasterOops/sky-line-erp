<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Stock;

class StockPolicy extends OwnedByCompanyPolicy
{
    public function view(User $authUser, Stock $stock): bool
    {
        return $this->belongsToSameCompany($authUser, $stock);
    }

    public function update(User $authUser, Stock $stock): bool
    {
        return $this->belongsToSameCompany($authUser, $stock);
    }

    public function delete(User $authUser, Stock $stock): bool
    {
        return $this->belongsToSameCompany($authUser, $stock);
    }
}

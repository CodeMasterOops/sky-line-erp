<?php

namespace App\Policies;

use App\Models\User;

abstract class OwnedByCompanyPolicy
{
    protected function belongsToSameCompany(User $authUser, mixed $model): bool
    {
        return $authUser->company_id === $model->company_id;
    }
}

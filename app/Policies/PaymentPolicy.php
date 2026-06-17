<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Payment;

class PaymentPolicy extends OwnedByCompanyPolicy
{
    public function view(User $authUser, Payment $payment): bool
    {
        return $this->belongsToSameCompany($authUser, $payment);
    }

    public function update(User $authUser, Payment $payment): bool
    {
        return $this->belongsToSameCompany($authUser, $payment);
    }

    public function delete(User $authUser, Payment $payment): bool
    {
        return $this->belongsToSameCompany($authUser, $payment);
    }
}

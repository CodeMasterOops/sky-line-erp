<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Invoice;

class InvoicePolicy extends OwnedByCompanyPolicy
{
    public function view(User $authUser, Invoice $invoice): bool
    {
        return $this->belongsToSameCompany($authUser, $invoice);
    }

    public function update(User $authUser, Invoice $invoice): bool
    {
        return $this->belongsToSameCompany($authUser, $invoice);
    }

    public function delete(User $authUser, Invoice $invoice): bool
    {
        return $this->belongsToSameCompany($authUser, $invoice);
    }
}

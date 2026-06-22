<?php

namespace App\Observers;

use App\Models\Party;
use App\Enums\PartyTypeEnum;
use App\Enums\CrmLeadStatusEnum;
use App\Enums\CrmActivityTypeEnum;
use App\Services\Crm\ActivityLogger;

/**
 * Keeps CRM lead data consistent for any party of type `lead`, regardless of
 * the creation path (Contacts create, the dedicated lead endpoint, imports or
 * seeders). A lead is just a Party with type=lead, so its profile and the
 * "lead created" timeline entry are provisioned here exactly once.
 */
class PartyObserver
{
    public function __construct(
        private ActivityLogger $activityLogger,
    ) {}

    public function created(Party $party): void
    {
        if ($party->type === PartyTypeEnum::LEAD) {
            $this->ensureLeadProfile($party);
        }
    }

    public function updated(Party $party): void
    {
        if ($party->type === PartyTypeEnum::LEAD && $party->wasChanged('type')) {
            $this->ensureLeadProfile($party);
        }
    }

    private function ensureLeadProfile(Party $party): void
    {
        if ($party->leadProfile()->exists()) {
            return;
        }

        $party->leadProfile()->create([
            'status' => CrmLeadStatusEnum::New->value,
            'created_by_user_id' => auth('admin')->id(),
        ]);

        $this->activityLogger->log($party, CrmActivityTypeEnum::LeadCreated, 'Lead created');
    }
}

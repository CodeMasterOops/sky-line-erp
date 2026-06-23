<?php

namespace App\Services\Crm;

use App\Models\Party;
use App\Enums\PartyTypeEnum;
use App\Enums\CrmLeadStatusEnum;
use App\Enums\CrmActivityTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadConverter
{
    public function __construct(
        private ActivityLogger $activityLogger,
    ) {}

    /**
     * Convert a lead Party into a customer, preserving all related history.
     *
     * Notes, contact persons, tags and activities key off the unchanged
     * party_id, so nothing needs to be moved — the conversion is a metadata
     * change. The lead profile is retained as the permanent origin record.
     */
    public function convert(Party $party): Party
    {
        if ($party->type !== PartyTypeEnum::LEAD) {
            throw ValidationException::withMessages([
                'party' => 'Only leads can be converted to customers.',
            ]);
        }

        return DB::transaction(function () use ($party) {
            $party->update(['type' => PartyTypeEnum::CUSTOMER]);

            $party->leadProfile?->update([
                'status' => CrmLeadStatusEnum::Converted,
                'converted_at' => now(),
            ]);

            $this->activityLogger->log(
                $party,
                CrmActivityTypeEnum::Converted,
                'Lead converted to customer',
            );

            return $party->refresh()->load(['leadProfile', 'contactPersons', 'tags']);
        });
    }
}

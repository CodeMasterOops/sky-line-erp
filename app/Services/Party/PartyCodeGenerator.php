<?php

namespace App\Services\Party;

use App\Models\Party;
use App\Enums\PartyTypeEnum;

class PartyCodeGenerator
{
    public function prefixFor(PartyTypeEnum $type): string
    {
        return match ($type) {
            PartyTypeEnum::CUSTOMER => 'CUST-',
            PartyTypeEnum::SUPPLIER => 'SUP-',
            PartyTypeEnum::LEAD => 'LEAD-',
        };
    }

    public function generate(PartyTypeEnum $type, int $companyId): string
    {
        $count = Party::query()
            ->where('company_id', $companyId)
            ->where('type', $type->value)
            ->withTrashed()
            ->count();

        return $this->prefixFor($type).str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}

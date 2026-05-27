<?php

namespace App\Services\Party;

use App\Models\Party;
use App\Enums\PartyTypeEnum;
use App\Services\EntityCodeGenerator;

class PartyCodeGenerator
{
    public function __construct(
        private EntityCodeGenerator $entityCodeGenerator,
    ) {}

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
        return $this->entityCodeGenerator->generate(
            Party::class,
            $companyId,
            $this->prefixFor($type),
            'code',
            4,
            ['type' => $type->value],
        );
    }
}

<?php

namespace App\Services\DataTransfer;

use App\Enums\DataTransfer\DataTransferEntityTypeEnum;

class DataTransferFieldRegistry
{
    /**
     * @return list<string>
     */
    public static function fieldsFor(DataTransferEntityTypeEnum $type): array
    {
        return match ($type) {
            DataTransferEntityTypeEnum::Product => config('data_transfer.product_fields', []),
            DataTransferEntityTypeEnum::Warehouse => config('data_transfer.warehouse_fields', []),
            DataTransferEntityTypeEnum::Party => config('data_transfer.party_fields', []),
            default => throw new \InvalidArgumentException("Import fields not defined for {$type->value}."),
        };
    }
}

<?php

namespace App\Services\DataTransfer\Import;

use App\Models\Party;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\DataTransfer\PartyImportService;
use App\Services\DataTransfer\WarehouseImportService;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;
use App\Services\DataTransfer\PartyImportRowValidator;
use App\Services\DataTransfer\ProductImportLookupCache;
use App\Services\DataTransfer\ProductImportRowValidator;
use App\Services\DataTransfer\WarehouseImportLookupCache;
use App\Services\DataTransfer\WarehouseImportRowValidator;

class ImportHandlerFactory
{
    public function validator(DataTransferEntityTypeEnum $type): ImportRowValidatorInterface
    {
        return match ($type) {
            DataTransferEntityTypeEnum::Product => app(ProductImportRowValidator::class),
            DataTransferEntityTypeEnum::Warehouse => app(WarehouseImportRowValidator::class),
            DataTransferEntityTypeEnum::Party => app(PartyImportRowValidator::class),
            default => throw new \InvalidArgumentException("Import not supported for {$type->value}."),
        };
    }

    public function service(DataTransferEntityTypeEnum $type): ImportServiceInterface
    {
        return match ($type) {
            DataTransferEntityTypeEnum::Product => app(ProductImportServiceAdapter::class),
            DataTransferEntityTypeEnum::Warehouse => app(WarehouseImportService::class),
            DataTransferEntityTypeEnum::Party => app(PartyImportService::class),
            default => throw new \InvalidArgumentException("Import not supported for {$type->value}."),
        };
    }

    public function lookups(DataTransferEntityTypeEnum $type, int $companyId): mixed
    {
        return match ($type) {
            DataTransferEntityTypeEnum::Product => ProductImportLookupCache::forCompany($companyId),
            DataTransferEntityTypeEnum::Warehouse => WarehouseImportLookupCache::forCompany($companyId),
            DataTransferEntityTypeEnum::Party => null,
            default => throw new \InvalidArgumentException("Import not supported for {$type->value}."),
        };
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public function targetModelClass(DataTransferEntityTypeEnum $type): string
    {
        return match ($type) {
            DataTransferEntityTypeEnum::Product => Product::class,
            DataTransferEntityTypeEnum::Warehouse => Warehouse::class,
            DataTransferEntityTypeEnum::Party => Party::class,
            default => throw new \InvalidArgumentException("Import not supported for {$type->value}."),
        };
    }

    public function supportsImport(DataTransferEntityTypeEnum $type): bool
    {
        return in_array($type, [
            DataTransferEntityTypeEnum::Product,
            DataTransferEntityTypeEnum::Warehouse,
            DataTransferEntityTypeEnum::Party,
        ], true);
    }
}

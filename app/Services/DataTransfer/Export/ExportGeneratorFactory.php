<?php

namespace App\Services\DataTransfer\Export;

use App\Enums\DataTransfer\DataTransferEntityTypeEnum;
use App\Services\DataTransfer\Export\Generators\BillExportGenerator;
use App\Services\DataTransfer\Export\Generators\PartyExportGenerator;
use App\Services\DataTransfer\Export\Generators\StockExportGenerator;
use App\Services\DataTransfer\Export\Generators\InvoiceExportGenerator;
use App\Services\DataTransfer\Export\Generators\ProductExportGenerator;
use App\Services\DataTransfer\Export\Generators\SalesOrderExportGenerator;
use App\Services\DataTransfer\Export\Generators\PurchaseOrderExportGenerator;

class ExportGeneratorFactory
{
    public function make(DataTransferEntityTypeEnum $type): ExportGeneratorInterface
    {
        return match ($type) {
            DataTransferEntityTypeEnum::Product => app(ProductExportGenerator::class),
            DataTransferEntityTypeEnum::Party => app(PartyExportGenerator::class),
            DataTransferEntityTypeEnum::Stock => app(StockExportGenerator::class),
            DataTransferEntityTypeEnum::Invoice => app(InvoiceExportGenerator::class),
            DataTransferEntityTypeEnum::Bill => app(BillExportGenerator::class),
            DataTransferEntityTypeEnum::SalesOrder => app(SalesOrderExportGenerator::class),
            DataTransferEntityTypeEnum::PurchaseOrder => app(PurchaseOrderExportGenerator::class),
            default => throw new \InvalidArgumentException("Export not supported for {$type->value}."),
        };
    }
}

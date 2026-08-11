<?php

namespace App\Enums\DataTransfer;

enum DataTransferEntityTypeEnum: string
{
    case Product = 'product';
    case Warehouse = 'warehouse';
    case Party = 'party';
    case Stock = 'stock';
    case OpeningStock = 'opening_stock';
    case Invoice = 'invoice';
    case Bill = 'bill';
    case SalesOrder = 'sales_order';
    case PurchaseOrder = 'purchase_order';
    case Account = 'account';
    case Journal = 'journal';

    /**
     * The module whose data this entity moves.
     *
     * Having the Data Import / Export module switched on does not entitle a
     * company to import products into an Inventory module it does not run —
     * that would create rows for a module it cannot see. `null` means the
     * entity is core (parties), gated by the data-transfer module alone.
     *
     * `config/modules.php` repeats this as `data_transfer_entities`;
     * ModuleCappingSurfaceTest asserts the two agree.
     */
    public function module(): ?string
    {
        return match ($this) {
            self::Party => null,
            self::Product, self::Warehouse, self::Stock, self::OpeningStock => 'inventory',
            self::Invoice, self::SalesOrder => 'sales',
            self::Bill, self::PurchaseOrder => 'purchase',
            self::Account, self::Journal => 'accounting',
        };
    }

    /**
     * The entity types the given company may transfer.
     *
     * @return list<self>
     */
    public static function enabledFor(int $companyId): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => $case->module() === null || moduleEnabled($case->module(), $companyId),
        ));
    }
}

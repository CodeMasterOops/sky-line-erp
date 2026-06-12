<?php

namespace App\Enums\DataTransfer;

enum DataTransferEntityTypeEnum: string
{
    case Product = 'product';
    case Warehouse = 'warehouse';
    case Party = 'party';
    case Stock = 'stock';
    case Invoice = 'invoice';
    case Bill = 'bill';
    case SalesOrder = 'sales_order';
    case PurchaseOrder = 'purchase_order';
    case Account = 'account';
    case Journal = 'journal';
}

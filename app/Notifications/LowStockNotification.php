<?php

namespace App\Notifications;

use App\Models\Stock;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Stock $stock,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $stock = $this->stock;
        $product = $stock->productVariant->product;
        $productName = $product->name;
        $warehouseName = $stock->warehouse->name;

        return [
            'type' => 'low_stock',
            'product_variant_id' => $stock->product_variant_id,
            'warehouse_id' => $stock->warehouse_id,
            'product_name' => $productName,
            'sku' => $stock->productVariant->sku,
            'warehouse_name' => $warehouseName,
            'quantity' => $stock->quantity,
            'min_stock_level' => $product->min_stock_level,
            'reorder_quantity' => $product->reorder_quantity,
            'message' => sprintf(
                '%s is below minimum stock level in %s. Current: %s, Min: %s.',
                $productName,
                $warehouseName,
                $stock->quantity,
                $product->min_stock_level,
            ),
        ];
    }
}

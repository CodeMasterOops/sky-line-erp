<?php

namespace App\Observers;

use App\Models\Stock;
use App\Jobs\CheckLowStockJob;

class StockObserver
{
    public function updated(Stock $stock): void
    {
        if (! $stock->isDirty('quantity')) {
            return;
        }

        if ($stock->quantity <= 0) {
            CheckLowStockJob::dispatch($stock);

            return;
        }

        $minLevel = (float) ($stock->productVariant?->product?->min_stock_level ?? 0);

        if (
            $minLevel > 0
            && $stock->quantity <= $minLevel
            && $stock->getOriginal('quantity') > $minLevel
        ) {
            CheckLowStockJob::dispatch($stock);
        }
    }
}

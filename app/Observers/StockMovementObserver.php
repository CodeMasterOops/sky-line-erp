<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Services\Accounting\StockMovementGlPostingService;

class StockMovementObserver
{
    public function created(StockMovement $stockMovement): void
    {
        app(StockMovementGlPostingService::class)->postFromMovement($stockMovement);
    }
}

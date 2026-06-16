<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Stock;
use Illuminate\Bus\Queueable;
use App\Enums\ProductTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CheckLowStockJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Stock $stock,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $stock = $this->stock->fresh(['productVariant.product', 'warehouse']);

        if (! $stock) {
            return;
        }

        $product = $stock->productVariant?->product;

        if (! $product || $product->product_type === ProductTypeEnum::SERVICE) {
            return;
        }

        $minStockLevel = (float) ($product->min_stock_level ?? 0);

        if ($minStockLevel <= 0) {
            return;
        }

        $alreadyNotified = DB::table('notifications')
            ->where('type', LowStockNotification::class)
            ->whereNull('read_at')
            ->whereRaw("JSON_EXTRACT(data, '$.product_variant_id') = ?", [$stock->product_variant_id])
            ->whereRaw("JSON_EXTRACT(data, '$.warehouse_id') = ?", [$stock->warehouse_id])
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        User::query()
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $stock->branch_id))
            ->get()
            ->each(fn (User $user) => $user->notify(new LowStockNotification($stock)));
    }
}

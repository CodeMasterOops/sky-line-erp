<?php

namespace App\Services\Inventory;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cascades a top-level BOM into draft sub-assembly production orders: for every manufactured
 * material component that is short of stock, creates an *unreserved* draft order for the
 * shortfall. Unreserved because the components don't exist yet — reserving would deadlock
 * (order creation hard-fails on insufficient stock). The user produces these, then builds
 * the parent. Must run inside a DB transaction.
 */
class SubAssemblyOrderService
{
    public function __construct(
        private ProductionOrderFactory $factory,
        private BomExplosionService $explosion,
    ) {}

    /**
     * @return Collection<int, \App\Models\ProductionOrder>
     */
    public function planFromBom(Bom $bom, float $qty, int $warehouseId, Company $company, int $userId): Collection
    {
        $bom->loadMissing('items');
        $ratio = $qty / ((float) $bom->output_qty ?: 1.0);
        $available = $this->availabilityMap($company->id);

        $created = collect();

        foreach ($bom->items as $item) {
            if ($item->item_type !== 'material') {
                continue;
            }

            $componentBom = $this->explosion->defaultBomFor($item->product_variant_id);
            if (! $componentBom) {
                continue; // not manufactured — replenish by purchase, not a sub-assembly order
            }

            $required = round($this->effectiveQty($item) * $ratio, 4);
            $shortfall = round($required - (float) ($available[$item->product_variant_id] ?? 0), 4);

            if ($shortfall <= 0) {
                continue;
            }

            $created->push(
                $this->factory->create($company, $componentBom, $warehouseId, $shortfall, $userId, [], reserve: false)
            );
        }

        return $created;
    }

    private function effectiveQty(BomItem $item): float
    {
        return (float) $item->quantity * (1 + (float) $item->wastage_pct / 100);
    }

    /**
     * @return array<int, float>
     */
    private function availabilityMap(int $companyId): array
    {
        return DB::table('stocks')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->groupBy('product_variant_id')
            ->selectRaw('product_variant_id, SUM(quantity - COALESCE(on_hold, 0)) as avail')
            ->pluck('avail', 'product_variant_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}

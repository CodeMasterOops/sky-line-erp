<?php

namespace App\Services\Inventory;

use App\Enums\StatusEnum;
use App\Enums\ProductTypeEnum;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight MRP: for each physical item, net requirement = min-stock buffer + open sales
 * demand, against available supply (on hand − held). Items short are proposed for production
 * (with an exploded material-shortfall list) when manufactured, otherwise purchase. Read-only.
 *
 * Open sales demand = quantities on sales orders in 'approved' or 'in_transit' status (this
 * codebase has no per-line fulfilment tracking, so committed-but-not-draft orders are "open").
 */
class MrpPlanningService
{
    private const OPEN_SALES_STATUSES = [
        StatusEnum::APPROVED->value,
        StatusEnum::IN_TRANSIT->value,
    ];

    public function __construct(private BomExplosionService $explosion) {}

    /**
     * @return array{generated_at: string, suggestions: list<array<string, mixed>>}
     */
    public function plan(int $companyId): array
    {
        $available = $this->availabilityMap($companyId);
        $demand = $this->salesDemandMap($companyId);
        $suggestions = [];

        foreach ($this->candidates($companyId, array_keys($demand)) as $variantId => $info) {
            $onHand = (float) ($available[$variantId] ?? 0);
            $openDemand = (float) ($demand[$variantId] ?? 0);
            $requirement = round((float) $info['min_stock'] + $openDemand, 4);
            $shortfall = round($requirement - $onHand, 4);

            if ($shortfall <= 0) {
                continue;
            }

            $bom = $this->explosion->defaultBomFor($variantId);

            $suggestion = [
                'variant_id' => $variantId,
                'name' => $info['name'],
                'sku' => $info['sku'],
                'code' => $info['code'],
                'on_hand' => $onHand,
                'min_stock' => (float) $info['min_stock'],
                'open_demand' => $openDemand,
                'requirement' => $requirement,
                'shortfall' => $shortfall,
                'action' => $bom ? 'produce' : 'purchase',
                'bom_id' => $bom?->id,
                'material_requirements' => [],
            ];

            if ($bom) {
                $explosion = $this->explosion->explode($bom, $shortfall);
                $suggestion['material_requirements'] = array_map(function (array $m) use ($available) {
                    $matOnHand = (float) ($available[$m['variant_id']] ?? 0);
                    $matShort = round($m['total_qty'] - $matOnHand, 4);

                    return [
                        'variant_id' => $m['variant_id'],
                        'name' => $m['name'],
                        'sku' => $m['sku'],
                        'required_qty' => $m['total_qty'],
                        'on_hand' => $matOnHand,
                        'shortfall' => max(0.0, $matShort),
                        'action' => $matShort > 0 ? 'purchase' : 'ok',
                    ];
                }, $explosion['materials']);
            }

            $suggestions[] = $suggestion;
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Available quantity (on hand minus held) per product variant.
     *
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

    /**
     * Open sales-order demand per product variant.
     *
     * @return array<int, float>
     */
    private function salesDemandMap(int $companyId): array
    {
        return DB::table('sales_order_items as soi')
            ->join('sales_orders as so', 'so.id', '=', 'soi.sales_order_id')
            ->where('so.company_id', $companyId)
            ->whereIn('so.status', self::OPEN_SALES_STATUSES)
            ->whereNull('so.deleted_at')
            ->whereNull('soi.deleted_at')
            ->groupBy('soi.product_variant_id')
            ->selectRaw('soi.product_variant_id, SUM(soi.quantity) as demand')
            ->pluck('demand', 'soi.product_variant_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Physical variants that either carry a minimum stock level or have open sales demand.
     *
     * @param  array<int, int>  $demandVariantIds
     * @return array<int, array{name: string, sku: string, code: string, min_stock: float}>
     */
    private function candidates(int $companyId, array $demandVariantIds): array
    {
        return DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.company_id', $companyId)
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.product_type', ProductTypeEnum::PRODUCT->value)
            ->where(function ($q) use ($demandVariantIds) {
                $q->where('p.min_stock_level', '>', 0);
                if ($demandVariantIds !== []) {
                    $q->orWhereIn('pv.id', $demandVariantIds);
                }
            })
            ->select('pv.id as variant_id', 'pv.sku', 'p.name', 'p.code', 'p.min_stock_level')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->variant_id => [
                'name' => $row->name,
                'sku' => $row->sku ?? '',
                'code' => $row->code ?? '',
                'min_stock' => (float) $row->min_stock_level,
            ]])
            ->all();
    }
}

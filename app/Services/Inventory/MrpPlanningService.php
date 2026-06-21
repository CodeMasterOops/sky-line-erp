<?php

namespace App\Services\Inventory;

use App\Enums\ProductTypeEnum;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight MRP: find items below their minimum stock level and propose how to
 * replenish each — a production order (with an exploded material-shortfall list) when
 * the item is manufactured, otherwise a purchase. Read-only; suggests, never commits.
 */
class MrpPlanningService
{
    public function __construct(private BomExplosionService $explosion) {}

    /**
     * @return array{generated_at: string, suggestions: list<array<string, mixed>>}
     */
    public function plan(int $companyId): array
    {
        $available = $this->availabilityMap($companyId);
        $suggestions = [];

        foreach ($this->belowMinimum($companyId, $available) as $row) {
            $variantId = (int) $row->variant_id;
            $onHand = (float) ($available[$variantId] ?? 0);
            $shortfall = round((float) $row->min_stock_level - $onHand, 4);

            $bom = $this->explosion->defaultBomFor($variantId);

            $suggestion = [
                'variant_id' => $variantId,
                'name' => $row->name,
                'sku' => $row->sku ?? '',
                'code' => $row->code ?? '',
                'on_hand' => $onHand,
                'min_stock' => (float) $row->min_stock_level,
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
     * Physical product variants whose available quantity is below the product's min stock level.
     *
     * @param  array<int, float>  $available
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function belowMinimum(int $companyId, array $available)
    {
        return DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('p.company_id', $companyId)
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.product_type', ProductTypeEnum::PRODUCT->value)
            ->where('p.min_stock_level', '>', 0)
            ->select('pv.id as variant_id', 'pv.sku', 'p.name', 'p.code', 'p.min_stock_level')
            ->get()
            ->filter(fn ($row) => ((float) ($available[(int) $row->variant_id] ?? 0)) < (float) $row->min_stock_level)
            ->values();
    }
}

<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Enums\StatusEnum;
use App\Models\SalesOrder;
use App\Enums\ChangeTypeEnum;
use App\Models\ProductVariant;
use App\Models\SalesOrderItem;
use App\Models\DeliveryChallan;
use Illuminate\Support\Facades\DB;
use App\Models\DeliveryChallanItem;
use Illuminate\Validation\ValidationException;

class DeliveryChallanService
{
    public function __construct(
        private InventoryLayerIssueService $inventoryIssue,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createChallan(array $data): DeliveryChallan
    {
        $user = auth('admin')->user();
        $company = $user->company;
        $status = $data['status'] ?? StatusEnum::DRAFT->value;
        $reference = $this->resolveReferencePayload($data);

        return DB::transaction(function () use ($data, $user, $company, $status, $reference) {
            $this->validateSalesOrderQuantities($data, $reference);

            $challan = DeliveryChallan::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $company->fiscal_year_id,
                'party_id' => $data['party_id'] ?? null,
                'reference_type' => $reference['reference_type'],
                'reference_id' => $reference['reference_id'],
                'warehouse_id' => $data['warehouse_id'],
                'challan_no' => $this->generateChallanNo($company->id),
                'challan_date' => $data['challan_date'],
                'delivery_address' => $data['delivery_address'] ?? null,
                'receiver_name' => $data['receiver_name'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => $status,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
            ]);

            $this->syncChallanItems($challan, $data['items'] ?? []);

            if ($status === StatusEnum::APPROVED->value) {
                $challan->refresh();
                $this->applyInventoryIssues($challan, $company, $user->id);
            }

            return $challan;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateChallan(DeliveryChallan $challan, array $data): DeliveryChallan
    {
        if ($challan->status === StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'challan' => __('Approved challan cannot be edited.'),
            ]);
        }

        $reference = $this->resolveReferencePayload($data, $challan);

        return DB::transaction(function () use ($challan, $data, $reference) {
            $this->validateSalesOrderQuantities($data, $reference, $challan->id);

            $challan->update([
                'party_id' => $data['party_id'] ?? null,
                'reference_type' => $reference['reference_type'],
                'reference_id' => $reference['reference_id'],
                'warehouse_id' => $data['warehouse_id'],
                'challan_date' => $data['challan_date'],
                'delivery_address' => $data['delivery_address'] ?? null,
                'receiver_name' => $data['receiver_name'] ?? null,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $challan->challanItems()->delete();
            $this->syncChallanItems($challan, $data['items'] ?? []);

            return $challan;
        });
    }

    public function approveChallan(DeliveryChallan $challan): DeliveryChallan
    {
        if ($challan->status === StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'challan' => __('Challan is already approved.'),
            ]);
        }

        $user = auth('admin')->user();
        $company = $user->company;

        return DB::transaction(function () use ($challan, $user, $company) {
            $challan->update([
                'status' => StatusEnum::APPROVED->value,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
            ]);

            $this->applyInventoryIssues($challan->fresh(), $company, $user->id);

            return $challan->fresh();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deliverableItemsForSalesOrder(SalesOrder $salesOrder): array
    {
        $salesOrder->loadMissing([
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
        ]);

        $deliveredBySoItem = $this->deliveredQuantitiesBySalesOrderItem($salesOrder->id);

        $items = [];

        foreach ($salesOrder->salesOrderItems as $soItem) {
            $soItem->loadMissing('productVariant.product');

            if ($soItem->productVariant?->isService()) {
                continue;
            }

            $orderedQty = (float) $soItem->quantity;
            $deliveredQty = (float) ($deliveredBySoItem[$soItem->id] ?? 0);
            $remainingQty = max(0, $orderedQty - $deliveredQty);

            if ($remainingQty <= 0) {
                continue;
            }

            $items[] = [
                'sales_order_item_id' => $soItem->id,
                'product_variant_id' => $soItem->product_variant_id,
                'product_variant' => $soItem->productVariant,
                'unit_id' => $soItem->unit_id,
                'ordered_qty' => $orderedQty,
                'delivered_qty' => $deliveredQty,
                'remaining_qty' => $remainingQty,
                'rate' => (float) $soItem->rate,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncChallanItems(DeliveryChallan $challan, array $items): void
    {
        $normalized = $this->normalizedChallanItems($items);

        foreach ($normalized as $item) {
            $challan->challanItems()->create($item);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizedChallanItems(array $items): array
    {
        $variantIds = collect($items)->pluck('product_variant_id')->unique()->filter()->all();
        $unitByVariantId = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->with('product:id,unit_id')
            ->get()
            ->keyBy('id')
            ->map(fn (ProductVariant $v) => $v->product?->unit_id);

        return collect($items)->map(function (array $item) use ($unitByVariantId) {
            $variantId = (int) $item['product_variant_id'];
            $fromRequest = $item['unit_id'] ?? null;
            $fallback = $unitByVariantId->get($variantId);
            $unitId = ($fromRequest !== null && $fromRequest !== '')
                ? (int) $fromRequest
                : ($fallback !== null && $fallback !== '' ? (int) $fallback : null);

            return [
                'product_variant_id' => $variantId,
                'sales_order_item_id' => ! empty($item['sales_order_item_id']) ? (int) $item['sales_order_item_id'] : null,
                'unit_id' => $unitId,
                'quantity' => (float) $item['quantity'],
                'rate' => (float) $item['rate'],
                'remarks' => $item['remarks'] ?? null,
            ];
        })->all();
    }

    private function applyInventoryIssues(DeliveryChallan $challan, Company $company, int $userId): void
    {
        $challan->loadMissing('challanItems.productVariant.product');

        foreach ($challan->challanItems as $item) {
            $qty = (int) round((float) $item->quantity);
            if ($qty <= 0) {
                continue;
            }

            $item->loadMissing('productVariant.product');
            if ($item->productVariant?->isService()) {
                continue;
            }

            $this->inventoryIssue->issue(
                $company,
                $challan,
                $item->product_variant_id,
                (int) $challan->warehouse_id,
                $qty,
                ChangeTypeEnum::DELIVERY,
                $userId,
                $challan->remarks,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{reference_type: ?string, reference_id: ?int}  $reference
     */
    private function validateSalesOrderQuantities(array $data, array $reference, ?int $excludeChallanId = null): void
    {
        if ($reference['reference_type'] !== SalesOrder::class || ! $reference['reference_id']) {
            return;
        }

        $salesOrderId = (int) $reference['reference_id'];
        $deliveredBySoItem = $this->deliveredQuantitiesBySalesOrderItem($salesOrderId, $excludeChallanId);

        foreach ($data['items'] ?? [] as $index => $item) {
            $soItemId = $item['sales_order_item_id'] ?? null;
            if (! $soItemId) {
                continue;
            }

            $soItem = SalesOrderItem::query()
                ->where('sales_order_id', $salesOrderId)
                ->where('id', $soItemId)
                ->first();

            if (! $soItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.sales_order_item_id" => __('Invalid sales order line.'),
                ]);
            }

            $orderedQty = (float) $soItem->quantity;
            $alreadyDelivered = (float) ($deliveredBySoItem[$soItemId] ?? 0);
            $remainingQty = max(0, $orderedQty - $alreadyDelivered);
            $requestedQty = (float) ($item['quantity'] ?? 0);

            if ($requestedQty > $remainingQty) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => __('Quantity exceeds remaining deliverable quantity (:remaining).', [
                        'remaining' => $remainingQty,
                    ]),
                ]);
            }
        }
    }

    /**
     * @return array<int, float>
     */
    private function deliveredQuantitiesBySalesOrderItem(int $salesOrderId, ?int $excludeChallanId = null): array
    {
        $query = DeliveryChallanItem::query()
            ->selectRaw('delivery_challan_items.sales_order_item_id, SUM(delivery_challan_items.quantity) as total_qty')
            ->join('delivery_challans', 'delivery_challans.id', '=', 'delivery_challan_items.delivery_challan_id')
            ->where('delivery_challans.reference_type', SalesOrder::class)
            ->where('delivery_challans.reference_id', $salesOrderId)
            ->where('delivery_challans.status', StatusEnum::APPROVED->value)
            ->whereNotNull('delivery_challan_items.sales_order_item_id')
            ->groupBy('delivery_challan_items.sales_order_item_id');

        if ($excludeChallanId) {
            $query->where('delivery_challans.id', '!=', $excludeChallanId);
        }

        return $query->pluck('total_qty', 'sales_order_item_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{reference_type: ?string, reference_id: ?int}
     */
    private function resolveReferencePayload(array $data, ?DeliveryChallan $challan = null): array
    {
        if (! empty($data['sales_order_id'])) {
            return [
                'reference_type' => SalesOrder::class,
                'reference_id' => (int) $data['sales_order_id'],
            ];
        }

        return [
            'reference_type' => $data['reference_type'] ?? $challan?->reference_type,
            'reference_id' => isset($data['reference_id'])
                ? (int) $data['reference_id']
                : $challan?->reference_id,
        ];
    }

    private function generateChallanNo(int $companyId): string
    {
        $count = DeliveryChallan::where('company_id', $companyId)->withTrashed()->count();

        return 'DC-'.str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    }
}

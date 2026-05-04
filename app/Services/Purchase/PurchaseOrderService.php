<?php

namespace App\Services\Purchase;

use App\Enums\StatusEnum;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use App\Enums\AmountOrPercentDiscountTypeEnum;

readonly class PurchaseOrderService
{
    public function __construct(
        private PurchaseOrderTotalsCalculator $totalsCalculator
    ) {}

    public function createPurchaseOrder(array $formData)
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $orderNo = $formData['order_no'] ?? $this->generateOrderNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $formData = $this->applyResolvedDiscounts($formData);

        $formData['fiscal_year_id'] = $fiscalYearId;
        $formData['order_no'] = $orderNo;
        $formData['create_user_id'] = $user->id;
        $formData['approve_user_id'] = $status === StatusEnum::APPROVED->value ? $user->id : null;
        $formData['approved_at'] = $status === StatusEnum::APPROVED->value ? now() : null;
        $formData['status'] = $status;

        return DB::transaction(function () use ($formData) {
            $items = $formData['items'];
            $orderDiscountType = $formData['order_discount_type'];
            $orderDiscountValue = $formData['order_discount_value'] ?? null;
            $orderDiscountAmount = $formData['order_discount_amount'];

            unset($formData['items'], $formData['order_discount_type'], $formData['order_discount_value'], $formData['order_discount_amount']);

            $order = PurchaseOrder::create($formData);

            $order->saveDiscount($orderDiscountType, $orderDiscountValue, $orderDiscountAmount);

            foreach ($items as $item) {
                $orderItem = $order->purchaseOrderItems()->create($item);

                $orderItem->saveDiscount(
                    $item['line_discount_type'],
                    isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                    $item['discount_amount'],
                );
            }

            return $order;
        });
    }

    public function updatePurchaseOrder(array $formData, PurchaseOrder $purchaseOrder): void
    {
        $formData = $this->applyResolvedDiscounts($formData);

        DB::transaction(function () use ($purchaseOrder, $formData) {
            $items = $formData['items'];
            $orderDiscountType = $formData['order_discount_type'];
            $orderDiscountValue = $formData['order_discount_value'] ?? null;
            $orderDiscountAmount = $formData['order_discount_amount'];

            unset($formData['items'], $formData['order_discount_type'], $formData['order_discount_value'], $formData['order_discount_amount']);

            $purchaseOrder->update($formData);

            $purchaseOrder->saveDiscount($orderDiscountType, $orderDiscountValue, $orderDiscountAmount);

            $purchaseOrder->purchaseOrderItems()->delete();

            foreach ($items as $item) {
                $orderItem = $purchaseOrder->purchaseOrderItems()->create($item);

                $orderItem->saveDiscount(
                    $item['line_discount_type'],
                    isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                    $item['discount_amount'],
                );
            }
        });
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    private function applyResolvedDiscounts(array $formData): array
    {
        $orderType = AmountOrPercentDiscountTypeEnum::tryFromString($formData['order_discount_type'] ?? null);
        $orderValue = (float) ($formData['order_discount_value'] ?? 0);

        $result = $this->totalsCalculator->resolveItemsAndOrderDiscount(
            $formData['items'],
            $orderType,
            $orderValue
        );

        $formData['items'] = $result['items'];
        $formData['order_discount_type'] = $orderType->value;
        if (array_key_exists('order_discount_value', $formData) && $formData['order_discount_value'] !== null) {
            $formData['order_discount_value'] = round((float) $formData['order_discount_value'], 2);
        }
        $formData['order_discount_amount'] = $result['order_discount_amount'];

        return $formData;
    }

    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        $user = auth('admin')->user();

        $purchaseOrder->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);
    }

    private function generateOrderNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = PurchaseOrder::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'PO-'.($count + 1).$suffix;
    }
}

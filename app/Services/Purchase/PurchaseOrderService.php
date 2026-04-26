<?php

namespace App\Services\Purchase;

use App\Enums\StatusEnum;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function createPurchaseOrder(array $formData)
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $orderNo = $formData['order_no'] ?? $this->generateOrderNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $formData['fiscal_year_id'] = $fiscalYearId;
        $formData['order_no'] = $orderNo;
        $formData['create_user_id'] = $user->id;
        $formData['approve_user_id'] = $status === StatusEnum::APPROVED->value ? $user->id : null;
        $formData['approved_at'] = $status === StatusEnum::APPROVED->value ? now() : null;
        $formData['status'] = $status;

        return DB::transaction(function () use ($formData) {
            $order = PurchaseOrder::create($formData);

            $order->purchaseOrderItems()->createMany($formData['items']);

            return $order;
        });
    }

    public function updatePurchaseOrder(array $formData, PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($purchaseOrder, $formData) {
            $purchaseOrder->update($formData);

            $purchaseOrder->purchaseOrderItems()->delete();

            $purchaseOrder->purchaseOrderItems()->createMany($formData['items']);
        });
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

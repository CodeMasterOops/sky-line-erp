<?php

namespace App\Services\Sales;

use App\Enums\StatusEnum;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;

readonly class SalesOrderService
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function createSalesOrder(array $formData): SalesOrder
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $setting) {
            // See InvoiceService for the lock-inside-transaction concurrency note.
            $orderNo = $formData['order_no'] ?? $this->documentNumberGenerator->fiscalYear(
                SalesOrder::class,
                'SO-',
                $fiscalYearId,
                $setting->fiscalYear?->year_code,
            );
            $order = SalesOrder::create([
                'company_id' => $user->company->id,
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'quotation_id' => $formData['quotation_id'] ?? null,
                'order_no' => $orderNo,
                'order_date' => $formData['order_date'],
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $order->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    0,
                );
            }

            foreach ($formData['items'] ?? [] as $item) {
                $orderItem = $order->salesOrderItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ]);

                if (isset($item['line_discount_type']) || isset($item['line_discount_value'])) {
                    $orderItem->saveDiscount(
                        $item['line_discount_type'] ?? 'fixed',
                        isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                        $item['discount_amount'] ?? 0,
                    );
                }
            }

            return $order;
        });
    }

    public function updateSalesOrder(array $formData, SalesOrder $salesOrder): void
    {
        $orderNo = $formData['order_no'] ?? $salesOrder->order_no;

        DB::transaction(function () use ($salesOrder, $formData, $orderNo) {
            $salesOrder->update([
                'party_id' => $formData['party_id'] ?? null,
                'quotation_id' => $formData['quotation_id'] ?? $salesOrder->quotation_id,
                'order_no' => $orderNo,
                'order_date' => $formData['order_date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $salesOrder->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    0,
                );
            }

            $salesOrder->salesOrderItems()->delete();

            foreach ($formData['items'] ?? [] as $item) {
                $orderItem = $salesOrder->salesOrderItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ]);

                if (isset($item['line_discount_type']) || isset($item['line_discount_value'])) {
                    $orderItem->saveDiscount(
                        $item['line_discount_type'] ?? 'fixed',
                        isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                        $item['discount_amount'] ?? 0,
                    );
                }
            }
        });
    }

    public function approveSalesOrder(SalesOrder $salesOrder): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($salesOrder, $user) {
            $salesOrder->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);
        });
    }
}

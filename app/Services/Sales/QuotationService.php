<?php

namespace App\Services\Sales;

use App\Enums\StatusEnum;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;

readonly class QuotationService
{
    public function createQuotation(array $formData): Quotation
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $quotationNo = $formData['quotation_no'] ?? $this->generateQuotationNo($fiscalYearId, $setting->fiscalYear?->year_code);

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $quotationNo) {
            $quotation = Quotation::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'quotation_no' => $quotationNo,
                'quotation_date' => $formData['quotation_date'],
                'expiry_date' => $formData['expiry_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $quotation->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    0,
                );
            }

            foreach ($formData['items'] ?? [] as $item) {
                $quotationItem = $quotation->quotationItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ]);

                if (isset($item['line_discount_type']) || isset($item['line_discount_value'])) {
                    $quotationItem->saveDiscount(
                        $item['line_discount_type'] ?? 'fixed',
                        isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                        $item['discount_amount'] ?? 0,
                    );
                }
            }

            return $quotation;
        });
    }

    public function updateQuotation(array $formData, Quotation $quotation): void
    {
        $quotationNo = $formData['quotation_no'] ?? $quotation->quotation_no;

        DB::transaction(function () use ($quotation, $formData, $quotationNo) {
            $quotation->update([
                'party_id' => $formData['party_id'] ?? null,
                'quotation_no' => $quotationNo,
                'quotation_date' => $formData['quotation_date'],
                'expiry_date' => $formData['expiry_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $quotation->quotationItems()->delete();

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $quotation->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    0,
                );
            }

            foreach ($formData['items'] ?? [] as $item) {
                $quotationItem = $quotation->quotationItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ]);

                if (isset($item['line_discount_type']) || isset($item['line_discount_value'])) {
                    $quotationItem->saveDiscount(
                        $item['line_discount_type'] ?? 'fixed',
                        isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                        $item['discount_amount'] ?? 0,
                    );
                }
            }
        });
    }

    public function approveQuotation(Quotation $quotation): void
    {
        $user = auth('admin')->user();

        $quotation->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);
    }

    private function generateQuotationNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Quotation::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'QT-'.($count + 1).$suffix;
    }

}

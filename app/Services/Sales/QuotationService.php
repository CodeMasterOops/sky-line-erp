<?php

namespace App\Services\Sales;

use App\Enums\StatusEnum;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentLineItemSyncer;
use App\Services\DocumentNumberGenerator;

readonly class QuotationService
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    public function createQuotation(array $formData): Quotation
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $setting) {
            // See InvoiceService for the lock-inside-transaction concurrency note.
            $quotationNo = $formData['quotation_no'] ?? $this->documentNumberGenerator->fiscalYear(
                Quotation::class,
                'QT-',
                $fiscalYearId,
                $setting->fiscalYear?->year_code,
            );
            $quotation = Quotation::create([
                'company_id' => $user->company->id,
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

            DocumentLineItemSyncer::sync(
                $quotation->quotationItems(),
                $formData['items'] ?? [],
                fn ($item) => [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ],
            );

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

            DocumentLineItemSyncer::sync(
                $quotation->quotationItems(),
                $formData['items'] ?? [],
                fn ($item) => [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ],
            );
        });
    }

    public function approveQuotation(Quotation $quotation): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($quotation, $user) {
            $quotation->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);
        });
    }
}

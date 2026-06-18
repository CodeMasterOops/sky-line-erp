<?php

namespace App\Services\Sales;

use App\Models\User;
use App\Models\Batch;
use App\Models\Company;
use App\Enums\StatusEnum;
use App\Models\CreditNote;
use App\Enums\ChangeTypeEnum;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentLineItemSyncer;
use App\Services\DocumentNumberGenerator;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalVoidService;
use App\Services\Accounting\CreditNoteGlPostingService;
use App\Services\Inventory\SalesReturnUnitCostResolver;
use App\Services\Inventory\InventoryLayerReceiptService;
use App\Services\Inventory\InventoryDocumentReversalService;

class CreditNoteService
{
    public function __construct(
        private InventoryLayerReceiptService $inventoryReceipt,
        private SalesReturnUnitCostResolver $salesReturnCostResolver,
        private InventoryDocumentReversalService $documentReversal,
        private DocumentNumberGenerator $documentNumberGenerator,
        private CreditNoteGlPostingService $creditNoteGl,
        private JournalVoidService $journalVoid,
    ) {}

    public function createCreditNote(array $formData, User $user): CreditNote
    {
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $fiscalYearId = $user->company->fiscal_year_id;

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId) {
            $creditNoteNo = $formData['credit_note_no'] ?? $this->documentNumberGenerator->fiscalYear(
                CreditNote::class,
                'CN-',
                $fiscalYearId,
                $user->company->fiscalYear?->year_code,
            );

            $creditNote = CreditNote::create([
                'company_id' => $user->company_id,
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'invoice_id' => $formData['invoice_id'] ?? null,
                'credit_note_no' => $creditNoteNo,
                'credit_note_date' => $formData['credit_note_date'],
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $creditNote->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    0,
                );
            }

            DocumentLineItemSyncer::sync(
                $creditNote->creditNoteItems(),
                $formData['items'] ?? [],
                fn ($item) => [
                    'invoice_item_id' => $item['invoice_item_id'] ?? null,
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                ],
            );

            if ($status === StatusEnum::APPROVED->value) {
                $creditNote->refresh();
                $this->applyInventory($creditNote, $user->company, $user);
                $this->creditNoteGl->postFromCreditNote($creditNote);
            }

            return $creditNote;
        });
    }

    public function updateCreditNote(array $formData, CreditNote $creditNote): CreditNote
    {
        $creditNoteNo = $formData['credit_note_no'] ?? $creditNote->credit_note_no;

        return DB::transaction(function () use ($creditNote, $formData, $creditNoteNo) {
            $creditNote->update([
                'party_id' => $formData['party_id'] ?? null,
                'invoice_id' => $formData['invoice_id'] ?? null,
                'credit_note_no' => $creditNoteNo,
                'credit_note_date' => $formData['credit_note_date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $creditNote->creditNoteItems()->delete();

            if (isset($formData['order_discount_type']) || isset($formData['order_discount_value'])) {
                $creditNote->saveDiscount(
                    $formData['order_discount_type'] ?? 'fixed',
                    isset($formData['order_discount_value']) ? (float) $formData['order_discount_value'] : null,
                    0,
                );
            }

            DocumentLineItemSyncer::sync(
                $creditNote->creditNoteItems(),
                $formData['items'] ?? [],
                fn ($item) => [
                    'invoice_item_id' => $item['invoice_item_id'] ?? null,
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                ],
            );

            return $creditNote;
        });
    }

    /**
     * @throws ValidationException
     */
    public function approveCreditNote(CreditNote $creditNote, User $user): void
    {
        DB::transaction(function () use ($creditNote, $user) {
            $creditNote->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->applyInventory($creditNote, $user->company, $user);
            $this->creditNoteGl->postFromCreditNote($creditNote);
        });
    }

    /**
     * @throws ValidationException
     */
    public function voidCreditNote(CreditNote $creditNote, User $user): void
    {
        DB::transaction(function () use ($creditNote, $user) {
            $this->documentReversal->reverseApprovedCreditNote(
                $creditNote,
                $user->company,
                $user->id,
                $creditNote->remarks,
            );
            $this->journalVoid->reverseForReference($creditNote);
            $creditNote->update(['voided_at' => now()]);
        });
    }

    private function applyInventory(CreditNote $creditNote, Company $company, User $user): void
    {
        $creditNote->loadMissing('creditNoteItems.productVariant.product');

        foreach ($creditNote->creditNoteItems as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            $item->loadMissing('productVariant.product');
            if ($item->productVariant?->isService()) {
                continue;
            }

            $unitCost = $this->salesReturnCostResolver->resolve(
                $company,
                $creditNote->invoice_id,
                $item->product_variant_id,
                $item->warehouse_id,
                $item->invoice_item_id,
            );

            $this->inventoryReceipt->receive(
                $company,
                $creditNote,
                $item->product_variant_id,
                $item->warehouse_id,
                $qty,
                $unitCost,
                ChangeTypeEnum::RETURN_IN,
                $user->id,
                $creditNote->remarks,
                null,
                batchId: $item->batch_id,
            );

            if ($item->batch_id) {
                Batch::where('id', $item->batch_id)->increment('remaining_qty', $qty);
                Batch::where('id', $item->batch_id)->where('status', 'depleted')->update(['status' => 'active']);
            }
        }
    }
}

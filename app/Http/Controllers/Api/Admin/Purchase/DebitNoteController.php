<?php

namespace App\Http\Controllers\Api\Admin\Purchase;

use App\Enums\StatusEnum;
use App\Models\DebitNote;
use Illuminate\Http\Request;
use App\Enums\ChangeTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\DocumentNumberGenerator;
use App\Enums\AmountOrPercentDiscountTypeEnum;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalVoidService;
use App\Services\Accounting\DebitNoteGlPostingService;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Http\Resources\Admin\Purchase\DebitNoteResource;
use App\Services\Purchase\PurchaseOrderTotalsCalculator;
use App\Http\Requests\Api\Admin\Purchase\DebitNoteRequest;
use App\Services\Inventory\InventoryDocumentReversalService;

class DebitNoteController extends Controller
{
    public function __construct(
        private InventoryLayerIssueService $inventoryIssue,
        private InventoryDocumentReversalService $documentReversal,
        private DocumentNumberGenerator $documentNumberGenerator,
        private DebitNoteGlPostingService $debitNoteGl,
        private JournalVoidService $journalVoid,
        private PurchaseOrderTotalsCalculator $totalsCalculator,
    ) {}

    #[Permissions('list_debit_note', group: 'debit_note', desc: 'List Debit Note')]
    public function index(Request $request)
    {
        $debitNotes = DebitNote::filter($request->all())
            ->with(['party', 'bill', 'discount', 'debitNoteItems.discount'])
            ->latest('debit_note_date')
            ->paginate($request->limit ?? 25);

        return DebitNoteResource::collection($debitNotes);
    }

    #[Permissions('create_debit_note', group: 'debit_note', desc: 'Create Debit Note')]
    public function store(DebitNoteRequest $request)
    {
        $formData = $this->applyResolvedDiscounts($request->validated());
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        try {
            $debitNote = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $setting) {
                // See InvoiceService for the lock-inside-transaction concurrency note.
                $debitNoteNo = $formData['debit_note_no'] ?? $this->documentNumberGenerator->fiscalYear(
                    DebitNote::class,
                    'DN-',
                    $fiscalYearId,
                    $setting->fiscalYear?->year_code,
                );
                $partyPan = isset($formData['party_id'])
                    ? \App\Models\Party::where('id', $formData['party_id'])->value('pan')
                    : null;

                $debitNote = DebitNote::create([
                    'company_id' => $user->company_id,
                    'fiscal_year_id' => $fiscalYearId,
                    'party_id' => $formData['party_id'] ?? null,
                    'supplier_pan' => $partyPan,
                    'bill_id' => $formData['bill_id'] ?? null,
                    'debit_note_no' => $debitNoteNo,
                    'debit_note_date' => $formData['debit_note_date'],
                    'remarks' => $formData['remarks'] ?? null,
                    'create_user_id' => $user->id,
                    'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                    'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                    'status' => $status,
                ]);

                $debitNote->saveDiscount(
                    $formData['order_discount_type'],
                    $formData['order_discount_value'] ?? null,
                    $formData['order_discount_amount'],
                );

                foreach ($formData['items'] ?? [] as $item) {
                    $debitNoteItem = $debitNote->debitNoteItems()->create([
                        'product_variant_id' => $item['product_variant_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'unit_id' => $item['unit_id'] ?? null,
                        'quantity' => $item['quantity'],
                        'rate' => $item['rate'],
                        'tax_id' => $item['tax_id'] ?? null,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                    ]);

                    $debitNoteItem->saveDiscount(
                        $item['line_discount_type'],
                        isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                        (float) ($item['discount_amount'] ?? 0),
                    );
                }

                if ($status === StatusEnum::APPROVED->value) {
                    $debitNote->refresh();
                    $this->applyInventoryForApprovedDebitNote($debitNote, $user->company, $user);
                    $this->debitNoteGl->postFromDebitNote($debitNote);
                }

                return $debitNote;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $debitNote->load([
            'party',
            'bill',
            'discount', 'debitNoteItems.discount', 'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => DebitNoteResource::make($debitNote),
            'message' => 'Debit Note Added Successfully',
        ], 201);
    }

    #[Permissions('show_debit_note', group: 'debit_note', desc: 'Show Debit Note')]
    public function show(DebitNote $debitNote)
    {
        $debitNote->load([
            'party',
            'bill',
            'discount', 'debitNoteItems.discount', 'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return DebitNoteResource::make($debitNote);
    }

    #[Permissions('edit_debit_note', group: 'debit_note', desc: 'Edit Debit Note')]
    public function update(DebitNoteRequest $request, DebitNote $debitNote)
    {
        if ($debitNote->voided_at) {
            return response()->json([
                'message' => 'Voided debit notes cannot be edited.',
            ], 422);
        }

        if ($debitNote->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved debit notes cannot be edited.',
            ], 422);
        }

        $formData = $this->applyResolvedDiscounts($request->validated());
        $debitNoteNo = $formData['debit_note_no'] ?? $debitNote->debit_note_no;

        $debitNote = DB::transaction(function () use ($debitNote, $formData, $debitNoteNo) {
            $debitNote->update([
                'party_id' => $formData['party_id'] ?? null,
                'bill_id' => $formData['bill_id'] ?? null,
                'debit_note_no' => $debitNoteNo,
                'debit_note_date' => $formData['debit_note_date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $debitNote->debitNoteItems()->delete();

            $debitNote->saveDiscount(
                $formData['order_discount_type'],
                $formData['order_discount_value'] ?? null,
                $formData['order_discount_amount'],
            );

            foreach ($formData['items'] ?? [] as $item) {
                $debitNoteItem = $debitNote->debitNoteItems()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ]);

                $debitNoteItem->saveDiscount(
                    $item['line_discount_type'],
                    isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
                    (float) ($item['discount_amount'] ?? 0),
                );
            }

            return $debitNote;
        });

        $debitNote->load([
            'party',
            'bill',
            'discount', 'debitNoteItems.discount', 'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => DebitNoteResource::make($debitNote),
            'message' => 'Debit Note Updated Successfully',
        ]);
    }

    #[Permissions('delete_debit_note', group: 'debit_note', desc: 'Delete Debit Note')]
    public function destroy(DebitNote $debitNote)
    {
        if ($debitNote->status === StatusEnum::APPROVED && ! $debitNote->voided_at) {
            return response()->json([
                'message' => __('Approved debit notes must be voided before they can be deleted.'),
            ], 422);
        }

        $debitNote->debitNoteItems()->delete();
        $debitNote->delete();

        return response()->json([
            'message' => 'Debit Note Deleted Successfully',
        ]);
    }

    #[Permissions('void_debit_note', group: 'debit_note', desc: 'Void Debit Note')]
    public function void(DebitNote $debitNote)
    {
        if ($debitNote->voided_at) {
            return response()->json([
                'data' => DebitNoteResource::make($debitNote),
                'message' => 'Debit note is already voided.',
            ]);
        }

        if ($debitNote->status !== StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Only approved debit notes can be voided.',
            ], 422);
        }

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($debitNote, $user) {
                $this->documentReversal->reverseApprovedDebitNote(
                    $debitNote,
                    $user->id,
                    $debitNote->remarks,
                );
                $this->journalVoid->reverseForReference($debitNote);
                $debitNote->update(['voided_at' => now()]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $debitNote->load([
            'party',
            'bill',
            'discount', 'debitNoteItems.discount', 'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => DebitNoteResource::make($debitNote),
            'message' => 'Debit note voided successfully.',
        ]);
    }

    #[Permissions('approve_debit_note', group: 'debit_note', desc: 'Approve Debit Note')]
    public function approve(DebitNote $debitNote)
    {
        if ($debitNote->voided_at) {
            return response()->json([
                'message' => 'Voided debit notes cannot be approved.',
            ], 422);
        }

        if ($debitNote->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => DebitNoteResource::make($debitNote),
                'message' => 'Debit Note Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($debitNote, $user) {
                $debitNote->update([
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED->value,
                ]);

                $this->applyInventoryForApprovedDebitNote($debitNote, $user->company, $user);
                $this->debitNoteGl->postFromDebitNote($debitNote);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $debitNote->load([
            'party',
            'bill',
            'discount', 'debitNoteItems.discount', 'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => DebitNoteResource::make($debitNote),
            'message' => 'Debit Note Approved Successfully',
        ]);
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
            $orderValue,
        );

        $formData['items'] = $result['items'];
        $formData['order_discount_type'] = $orderType->value;
        if (array_key_exists('order_discount_value', $formData) && $formData['order_discount_value'] !== null) {
            $formData['order_discount_value'] = round((float) $formData['order_discount_value'], 2);
        }
        $formData['order_discount_amount'] = $result['order_discount_amount'];

        return $formData;
    }

    private function applyInventoryForApprovedDebitNote(DebitNote $debitNote, \App\Models\Company $company, \App\Models\User $user): void
    {
        $debitNote->loadMissing('debitNoteItems');

        foreach ($debitNote->debitNoteItems as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            $this->inventoryIssue->issue(
                $company,
                $debitNote,
                $item->product_variant_id,
                $item->warehouse_id,
                $qty,
                ChangeTypeEnum::RETURN_OUT,
                $user->id,
                $debitNote->remarks,
            );
        }
    }
}

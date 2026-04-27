<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\StatusEnum;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use App\Enums\ChangeTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Admin\CreditNoteResource;
use App\Http\Requests\Api\Admin\CreditNoteRequest;
use App\Services\Inventory\SalesReturnUnitCostResolver;
use App\Services\Inventory\InventoryLayerReceiptService;
use App\Services\Inventory\InventoryDocumentReversalService;

class CreditNoteController extends Controller
{
    public function __construct(
        private InventoryLayerReceiptService $inventoryReceipt,
        private SalesReturnUnitCostResolver $salesReturnCostResolver,
        private InventoryDocumentReversalService $documentReversal,
    ) {}

    /**
     * @Permissions("list_credit_note", group="credit_note", desc="List Credit Note")
     */
    public function index(Request $request)
    {
        $creditNotes = CreditNote::filter($request->all())
            ->with(['party', 'invoice', 'creditNoteItems'])
            ->latest('credit_note_date')
            ->paginate($request->limit ?? 25);

        return CreditNoteResource::collection($creditNotes);
    }

    /**
     * @Permissions("create_credit_note", group="credit_note", desc="Create Credit Note")
     */
    public function store(CreditNoteRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $creditNoteNo = $formData['credit_note_no'] ?? $this->generateCreditNoteNo($fiscalYearId, $setting->fiscalYear?->year_code);

        try {
            $creditNote = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $creditNoteNo) {
                $creditNote = CreditNote::create([
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

                $items = collect($formData['items'] ?? [])->map(function ($item) {
                    return [
                        'invoice_item_id' => $item['invoice_item_id'] ?? null,
                        'product_variant_id' => $item['product_variant_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'unit_id' => $item['unit_id'] ?? null,
                        'quantity' => $item['quantity'],
                        'rate' => $item['rate'],
                        'tax_id' => $item['tax_id'] ?? null,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                    ];
                })->all();

                $creditNote->creditNoteItems()->createMany($items);

                if ($status === StatusEnum::APPROVED->value) {
                    $creditNote->refresh();
                    $this->applyInventoryForApprovedCreditNote($creditNote, $user->company, $user);
                }

                return $creditNote;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $creditNote->load([
            'party',
            'invoice',
            'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => CreditNoteResource::make($creditNote),
            'message' => 'Credit Note Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_credit_note", group="credit_note", desc="Show Credit Note")
     */
    public function show(CreditNote $creditNote)
    {
        $creditNote->load([
            'party',
            'invoice',
            'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return CreditNoteResource::make($creditNote);
    }

    /**
     * @Permissions("edit_credit_note", group="credit_note", desc="Edit Credit Note")
     */
    public function update(CreditNoteRequest $request, CreditNote $creditNote)
    {
        if ($creditNote->voided_at) {
            return response()->json([
                'message' => 'Voided credit notes cannot be edited.',
            ], 422);
        }

        if ($creditNote->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved credit notes cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();
        $creditNoteNo = $formData['credit_note_no'] ?? $creditNote->credit_note_no;

        $creditNote = DB::transaction(function () use ($creditNote, $formData, $creditNoteNo) {
            $creditNote->update([
                'party_id' => $formData['party_id'] ?? null,
                'invoice_id' => $formData['invoice_id'] ?? null,
                'credit_note_no' => $creditNoteNo,
                'credit_note_date' => $formData['credit_note_date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $creditNote->creditNoteItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'invoice_item_id' => $item['invoice_item_id'] ?? null,
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $creditNote->creditNoteItems()->createMany($items);

            return $creditNote;
        });

        $creditNote->load([
            'party',
            'invoice',
            'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => CreditNoteResource::make($creditNote),
            'message' => 'Credit Note Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_credit_note", group="credit_note", desc="Delete Credit Note")
     */
    public function destroy(CreditNote $creditNote)
    {
        if ($creditNote->status === StatusEnum::APPROVED && ! $creditNote->voided_at) {
            return response()->json([
                'message' => __('Approved credit notes must be voided before they can be deleted.'),
            ], 422);
        }

        $creditNote->creditNoteItems()->delete();
        $creditNote->delete();

        return response()->json([
            'message' => 'Credit Note Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_credit_note", group="credit_note", desc="Void Credit Note")
     */
    public function void(CreditNote $creditNote)
    {
        if ($creditNote->voided_at) {
            return response()->json([
                'data' => CreditNoteResource::make($creditNote),
                'message' => 'Credit note is already voided.',
            ]);
        }

        if ($creditNote->status !== StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Only approved credit notes can be voided.',
            ], 422);
        }

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($creditNote, $user) {
                $this->documentReversal->reverseApprovedCreditNote(
                    $creditNote,
                    $user->company,
                    $user->id,
                    $creditNote->remarks,
                );
                $creditNote->update(['voided_at' => now()]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $creditNote->load([
            'party',
            'invoice',
            'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => CreditNoteResource::make($creditNote),
            'message' => 'Credit note voided successfully.',
        ]);
    }

    /**
     * @Permissions("approve_credit_note", group="credit_note", desc="Approve Credit Note")
     */
    public function approve(CreditNote $creditNote)
    {
        if ($creditNote->voided_at) {
            return response()->json([
                'message' => 'Voided credit notes cannot be approved.',
            ], 422);
        }

        if ($creditNote->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => CreditNoteResource::make($creditNote),
                'message' => 'Credit Note Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($creditNote, $user) {
                $creditNote->update([
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED->value,
                ]);

                $this->applyInventoryForApprovedCreditNote($creditNote, $user->company, $user);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $creditNote->load([
            'party',
            'invoice',
            'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => CreditNoteResource::make($creditNote),
            'message' => 'Credit Note Approved Successfully',
        ]);
    }

    private function applyInventoryForApprovedCreditNote(CreditNote $creditNote, \App\Models\Company $company, \App\Models\User $user): void
    {
        $creditNote->loadMissing('creditNoteItems');

        foreach ($creditNote->creditNoteItems as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
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
            );
        }
    }

    private function generateCreditNoteNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = CreditNote::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'CN-'.($count + 1).$suffix;
    }
}

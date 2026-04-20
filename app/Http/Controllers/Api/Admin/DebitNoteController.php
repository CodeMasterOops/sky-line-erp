<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\StatusEnum;
use App\Models\DebitNote;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DebitNoteResource;
use App\Http\Requests\Api\Admin\DebitNoteRequest;

class DebitNoteController extends Controller
{
    /**
     * @Permissions("list_debit_note", group="debit_note", desc="List Debit Note")
     */
    public function index(Request $request)
    {
        $debitNotes = DebitNote::filter($request->all())
            ->with(['party', 'bill', 'debitNoteItems'])
            ->latest('debit_note_date')
            ->paginate($request->limit ?? 25);

        return DebitNoteResource::collection($debitNotes);
    }

    /**
     * @Permissions("create_debit_note", group="debit_note", desc="Create Debit Note")
     */
    public function store(DebitNoteRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $debitNoteNo = $formData['debit_note_no'] ?? $this->generateDebitNoteNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $debitNote = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $debitNoteNo) {
            $debitNote = DebitNote::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'bill_id' => $formData['bill_id'] ?? null,
                'debit_note_no' => $debitNoteNo,
                'debit_note_date' => $formData['debit_note_date'],
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
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

            $debitNote->debitNoteItems()->createMany($items);

            return $debitNote;
        });

        $debitNote->load([
            'party',
            'bill',
            'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => DebitNoteResource::make($debitNote),
            'message' => 'Debit Note Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_debit_note", group="debit_note", desc="Show Debit Note")
     */
    public function show(DebitNote $debitNote)
    {
        $debitNote->load([
            'party',
            'bill',
            'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return DebitNoteResource::make($debitNote);
    }

    /**
     * @Permissions("edit_debit_note", group="debit_note", desc="Edit Debit Note")
     */
    public function update(DebitNoteRequest $request, DebitNote $debitNote)
    {
        if ($debitNote->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved debit notes cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();
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

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
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

            $debitNote->debitNoteItems()->createMany($items);

            return $debitNote;
        });

        $debitNote->load([
            'party',
            'bill',
            'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => DebitNoteResource::make($debitNote),
            'message' => 'Debit Note Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_debit_note", group="debit_note", desc="Delete Debit Note")
     */
    public function destroy(DebitNote $debitNote)
    {
        $debitNote->debitNoteItems()->delete();
        $debitNote->delete();

        return response()->json([
            'message' => 'Debit Note Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_debit_note", group="debit_note", desc="Approve Debit Note")
     */
    public function approve(DebitNote $debitNote)
    {
        if ($debitNote->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => DebitNoteResource::make($debitNote),
                'message' => 'Debit Note Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        $debitNote->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $debitNote->load([
            'party',
            'bill',
            'debitNoteItems.productVariant.product',
            'debitNoteItems.unit',
            'debitNoteItems.tax',
            'debitNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => DebitNoteResource::make($debitNote),
            'message' => 'Debit Note Approved Successfully',
        ]);
    }

    private function generateDebitNoteNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = DebitNote::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'DN-'.($count + 1).$suffix;
    }
}

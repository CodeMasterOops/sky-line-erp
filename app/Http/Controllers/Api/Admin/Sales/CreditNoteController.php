<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use App\Enums\StatusEnum;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Sales\CreditNoteService;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Admin\Sales\CreditNoteResource;
use App\Http\Requests\Api\Admin\Sales\CreditNoteRequest;

class CreditNoteController extends Controller
{
    public function __construct(
        private CreditNoteService $creditNoteService,
    ) {}

    #[Permissions('list_credit_note', group: 'credit_note', desc: 'List Credit Note')]
    public function index(Request $request)
    {
        $creditNotes = CreditNote::filter($request->all())
            ->with(['party', 'invoice', 'discount', 'creditNoteItems.discount'])
            ->latest('credit_note_date')
            ->paginate($request->limit ?? 25);

        return CreditNoteResource::collection($creditNotes);
    }

    #[Permissions('create_credit_note', group: 'credit_note', desc: 'Create Credit Note')]
    public function store(CreditNoteRequest $request)
    {
        $user = auth('admin')->user();

        try {
            $creditNote = $this->creditNoteService->createCreditNote($request->validated(), $user);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $creditNote->load([
            'party',
            'invoice',
            'discount', 'creditNoteItems.discount', 'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => CreditNoteResource::make($creditNote),
            'message' => 'Credit Note Added Successfully',
        ], 201);
    }

    #[Permissions('show_credit_note', group: 'credit_note', desc: 'Show Credit Note')]
    public function show(CreditNote $creditNote)
    {
        $creditNote->load([
            'party',
            'invoice',
            'discount', 'creditNoteItems.discount', 'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return CreditNoteResource::make($creditNote);
    }

    #[Permissions('edit_credit_note', group: 'credit_note', desc: 'Edit Credit Note')]
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

        $creditNote = $this->creditNoteService->updateCreditNote($request->validated(), $creditNote);

        $creditNote->load([
            'party',
            'invoice',
            'discount', 'creditNoteItems.discount', 'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => CreditNoteResource::make($creditNote),
            'message' => 'Credit Note Updated Successfully',
        ]);
    }

    #[Permissions('delete_credit_note', group: 'credit_note', desc: 'Delete Credit Note')]
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

    #[Permissions('void_credit_note', group: 'credit_note', desc: 'Void Credit Note')]
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
            $this->creditNoteService->voidCreditNote($creditNote, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $creditNote->load([
            'party',
            'invoice',
            'discount', 'creditNoteItems.discount', 'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => CreditNoteResource::make($creditNote),
            'message' => 'Credit note voided successfully.',
        ]);
    }

    #[Permissions('approve_credit_note', group: 'credit_note', desc: 'Approve Credit Note')]
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
            $this->creditNoteService->approveCreditNote($creditNote, $user);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $creditNote->load([
            'party',
            'invoice',
            'discount', 'creditNoteItems.discount', 'creditNoteItems.productVariant.product',
            'creditNoteItems.unit',
            'creditNoteItems.tax',
            'creditNoteItems.warehouse',
        ]);

        return response()->json([
            'data' => CreditNoteResource::make($creditNote),
            'message' => 'Credit Note Approved Successfully',
        ]);
    }
}

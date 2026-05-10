<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Cheque;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Accounting\ChequeResource;
use App\Http\Requests\Api\Admin\Accounting\ChequeRequest;
use App\Http\Requests\Api\Admin\Accounting\ChequeClearRequest;
use App\Http\Requests\Api\Admin\Accounting\ChequeBounceRequest;
use App\Http\Requests\Api\Admin\Accounting\ChequePresentRequest;

class ChequeController extends Controller
{
    /**
     * @Permissions("list_cheque", group="cheque", desc="List Cheques / PDC")
     */
    public function index(Request $request)
    {
        $cheques = Cheque::query()
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from_date, fn ($q, $d) => $q->whereDate('cheque_date', '>=', $d))
            ->when($request->to_date, fn ($q, $d) => $q->whereDate('cheque_date', '<=', $d))
            ->with(['party:id,name', 'bankAccount:id,bank_name,account_number', 'createUser:id,name'])
            ->orderByDesc('cheque_date')
            ->paginate($request->per_page ?? 25);

        return ChequeResource::collection($cheques);
    }

    /**
     * @Permissions("create_cheque", group="cheque", desc="Record PDC Cheque")
     */
    public function store(ChequeRequest $request)
    {
        $data = $request->validated();
        if (($data['type'] ?? null) === 'receivable') {
            $data['bank_account_id'] = null;
        }

        if (($data['type'] ?? null) === 'payable') {
            $data['bank_name'] = null;
        }

        $company = auth('admin')->user()->company;
        $fiscalYear = $company->fiscalYear;

        $cheque = Cheque::create([
            ...$data,
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'status' => 'pending',
            'create_user_id' => auth('admin')->id(),
        ]);

        $cheque->load(['party:id,name', 'bankAccount:id,bank_name,account_number', 'createUser:id,name']);

        return response()->json([
            'data' => ChequeResource::make($cheque),
            'message' => 'Cheque recorded successfully',
        ], 201);
    }

    /**
     * @Permissions("show_cheque", group="cheque", desc="Show Cheque")
     */
    public function show(Cheque $cheque)
    {
        $cheque->load(['party:id,name', 'bankAccount:id,bank_name,account_number', 'createUser:id,name']);

        return response()->json(['data' => ChequeResource::make($cheque)]);
    }

    /**
     * @Permissions("edit_cheque", group="cheque", desc="Present Cheque for Deposit")
     */
    public function present(ChequePresentRequest $request, Cheque $cheque)
    {
        abort_if($cheque->status !== 'pending', 422, 'Only pending cheques can be presented.');

        $data = $request->validated();

        $cheque->update(['status' => 'presented', 'deposit_date' => $data['deposit_date']]);

        $cheque->load(['party:id,name', 'bankAccount:id,bank_name,account_number', 'createUser:id,name']);

        return response()->json([
            'message' => 'Cheque marked as presented.',
            'data' => ChequeResource::make($cheque),
        ]);
    }

    /**
     * @Permissions("edit_cheque", group="cheque", desc="Clear Cheque")
     */
    public function clear(ChequeClearRequest $request, Cheque $cheque)
    {
        abort_if(! in_array($cheque->status, ['pending', 'presented']), 422, 'Cheque cannot be cleared in current status.');

        $data = $request->validated();

        $cheque->update(['status' => 'cleared', 'cleared_date' => $data['cleared_date']]);

        $cheque->load(['party:id,name', 'bankAccount:id,bank_name,account_number', 'createUser:id,name']);

        return response()->json([
            'message' => 'Cheque cleared successfully.',
            'data' => ChequeResource::make($cheque),
        ]);
    }

    /**
     * @Permissions("edit_cheque", group="cheque", desc="Mark Cheque Bounced")
     */
    public function bounce(ChequeBounceRequest $request, Cheque $cheque)
    {
        abort_if(! in_array($cheque->status, ['presented']), 422, 'Only presented cheques can be bounced.');

        $data = $request->validated();

        $cheque->update(['status' => 'bounced', 'remarks' => $data['remarks'] ?? $cheque->remarks]);

        $cheque->load(['party:id,name', 'bankAccount:id,bank_name,account_number', 'createUser:id,name']);

        return response()->json([
            'message' => 'Cheque marked as bounced.',
            'data' => ChequeResource::make($cheque),
        ]);
    }

    /**
     * @Permissions("edit_cheque", group="cheque", desc="Cancel Cheque")
     */
    public function cancel(Cheque $cheque)
    {
        abort_if($cheque->status === 'cleared', 422, 'Cleared cheques cannot be cancelled.');

        $cheque->update(['status' => 'cancelled']);

        $cheque->load(['party:id,name', 'bankAccount:id,bank_name,account_number', 'createUser:id,name']);

        return response()->json([
            'message' => 'Cheque cancelled.',
            'data' => ChequeResource::make($cheque),
        ]);
    }

    /**
     * @Permissions("list_cheque", group="cheque", desc="PDC Summary")
     */
    public function summary()
    {
        $company = auth('admin')->user()->company;

        $data = Cheque::where('company_id', $company->id)
            ->selectRaw('type, status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('type', 'status')
            ->get();

        $dueThisWeek = Cheque::where('company_id', $company->id)
            ->dueForPresentation(7)
            ->with(['party:id,name', 'bankAccount:id,bank_name,account_number', 'createUser:id,name'])
            ->orderBy('cheque_date')
            ->get();

        return response()->json([
            'data' => $data,
            'due_this_week' => ChequeResource::collection($dueThisWeek),
        ]);
    }
}

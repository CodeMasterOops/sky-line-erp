<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Cheque;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;

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

        return response()->json($cheques);
    }

    /**
     * @Permissions("create_cheque", group="cheque", desc="Record PDC Cheque")
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'party_id' => 'nullable|exists:parties,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'cheque_no' => 'required|string|max:50',
            'bank_name' => 'nullable|string|max:150',
            'bank_branch' => 'nullable|string|max:100',
            'cheque_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:payable,receivable',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'remarks' => 'nullable|string',
        ]);

        $company = auth()->user()->company;
        $fiscalYear = $company->fiscalYear;

        $cheque = Cheque::create([
            ...$data,
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYear->id,
            'status' => 'pending',
            'create_user_id' => auth()->id(),
        ]);

        return response()->json(['data' => $cheque->load('party:id,name'), 'message' => 'Cheque recorded successfully'], 201);
    }

    /**
     * @Permissions("show_cheque", group="cheque", desc="Show Cheque")
     */
    public function show(Cheque $cheque)
    {
        return response()->json(['data' => $cheque->load(['party', 'bankAccount', 'createUser:id,name'])]);
    }

    /**
     * @Permissions("edit_cheque", group="cheque", desc="Present Cheque for Deposit")
     */
    public function present(Request $request, Cheque $cheque)
    {
        abort_if($cheque->status !== 'pending', 422, 'Only pending cheques can be presented.');

        $data = $request->validate(['deposit_date' => 'required|date']);

        $cheque->update(['status' => 'presented', 'deposit_date' => $data['deposit_date']]);

        return response()->json(['message' => 'Cheque marked as presented.', 'data' => $cheque]);
    }

    /**
     * @Permissions("edit_cheque", group="cheque", desc="Clear Cheque")
     */
    public function clear(Request $request, Cheque $cheque)
    {
        abort_if(! in_array($cheque->status, ['pending', 'presented']), 422, 'Cheque cannot be cleared in current status.');

        $data = $request->validate(['cleared_date' => 'required|date']);

        $cheque->update(['status' => 'cleared', 'cleared_date' => $data['cleared_date']]);

        return response()->json(['message' => 'Cheque cleared successfully.', 'data' => $cheque]);
    }

    /**
     * @Permissions("edit_cheque", group="cheque", desc="Mark Cheque Bounced")
     */
    public function bounce(Request $request, Cheque $cheque)
    {
        abort_if(! in_array($cheque->status, ['presented']), 422, 'Only presented cheques can be bounced.');

        $data = $request->validate(['remarks' => 'nullable|string']);

        $cheque->update(['status' => 'bounced', 'remarks' => $data['remarks'] ?? $cheque->remarks]);

        return response()->json(['message' => 'Cheque marked as bounced.', 'data' => $cheque]);
    }

    /**
     * @Permissions("edit_cheque", group="cheque", desc="Cancel Cheque")
     */
    public function cancel(Cheque $cheque)
    {
        abort_if($cheque->status === 'cleared', 422, 'Cleared cheques cannot be cancelled.');

        $cheque->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Cheque cancelled.']);
    }

    /**
     * @Permissions("list_cheque", group="cheque", desc="PDC Summary")
     */
    public function summary()
    {
        $company = auth()->user()->company;

        $data = Cheque::where('company_id', $company->id)
            ->selectRaw('type, status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('type', 'status')
            ->get();

        $dueThisWeek = Cheque::where('company_id', $company->id)
            ->dueForPresentation(7)
            ->with('party:id,name')
            ->orderBy('cheque_date')
            ->get(['id', 'cheque_no', 'party_id', 'cheque_date', 'amount', 'type']);

        return response()->json([
            'data' => $data,
            'due_this_week' => $dueThisWeek,
        ]);
    }
}

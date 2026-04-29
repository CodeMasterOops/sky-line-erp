<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Expense;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Accounting\ExpenseService;
use App\Http\Resources\Admin\ExpenseResource;
use App\Http\Requests\Api\Admin\ExpenseRequest;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    /**
     * @Permissions("list_expense", group="expense", desc="List Expense")
     */
    public function index(Request $request)
    {
        $expenses = Expense::filter($request->all())
            ->with(['party', 'discount', 'expenseItems.discount'])
            ->latest('date')
            ->paginate($request->limit ?? 25);

        return ExpenseResource::collection($expenses);
    }

    /**
     * @Permissions("create_expense", group="expense", desc="Create Expense")
     */
    public function store(ExpenseRequest $request)
    {
        $expense = $this->expenseService->createExpense($request->validated());

        $expense->load([
            'party',
            'discount', 'expenseItems.discount', 'expenseItems.account',
            'expenseItems.tax',
        ]);

        return response()->json([
            'data' => ExpenseResource::make($expense),
            'message' => 'Expense Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_expense", group="expense", desc="Show Expense")
     */
    public function show(Expense $expense)
    {
        $expense->load([
            'party',
            'discount', 'expenseItems.discount', 'expenseItems.account',
            'expenseItems.tax',
        ]);

        return ExpenseResource::make($expense);
    }

    /**
     * @Permissions("edit_expense", group="expense", desc="Edit Expense")
     */
    public function update(ExpenseRequest $request, Expense $expense)
    {
        if ($expense->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved expenses cannot be edited.',
            ], 422);
        }

        $this->expenseService->updateExpense($request->validated(), $expense);

        $expense->load([
            'party',
            'discount', 'expenseItems.discount', 'expenseItems.account',
            'expenseItems.tax',
        ]);

        return response()->json([
            'data' => ExpenseResource::make($expense),
            'message' => 'Expense Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_expense", group="expense", desc="Delete Expense")
     */
    public function destroy(Expense $expense)
    {
        $expense->expenseItems()->delete();
        $expense->delete();

        return response()->json([
            'message' => 'Expense Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_expense", group="expense", desc="Approve Expense")
     */
    public function approve(Expense $expense)
    {
        if ($expense->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => ExpenseResource::make($expense),
                'message' => 'Expense Already Approved',
            ]);
        }

        $this->expenseService->approveExpense($expense);

        $expense->load([
            'party',
            'discount', 'expenseItems.discount', 'expenseItems.account',
            'expenseItems.tax',
        ]);

        return response()->json([
            'data' => ExpenseResource::make($expense),
            'message' => 'Expense Approved Successfully',
        ]);
    }

    /**
     * @Permissions("list_due_expenses", group="expense", desc="List Due Expenses By Party")
     */
    public function dueExpenses(Request $request)
    {
        $partyId = (int) $request->get('party_id');
        if (! $partyId) {
            return response()->json([
                'message' => 'party_id is required.',
            ], 422);
        }

        $itemsSub = DB::table('expense_items')
            ->selectRaw('expense_id, SUM(amount) as subtotal, SUM(discount_amount) as discount_total, SUM(tax_amount) as tax_total')
            ->whereNull('deleted_at')
            ->groupBy('expense_id');

        $paidSub = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->selectRaw('payment_allocations.payable_id, SUM(payment_allocations.amount) as paid_total')
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->where('payments.status', StatusEnum::APPROVED->value)
            ->where('payment_allocations.payable_type', 'expense')
            ->groupBy('payment_allocations.payable_id');

        $rows = DB::table('expenses')
            ->leftJoinSub($itemsSub, 'item_totals', function ($join) {
                $join->on('expenses.id', '=', 'item_totals.expense_id');
            })
            ->leftJoinSub($paidSub, 'paid_totals', function ($join) {
                $join->on('expenses.id', '=', 'paid_totals.payable_id');
            })
            ->where('expenses.party_id', $partyId)
            ->where('expenses.status', StatusEnum::APPROVED->value)
            ->whereNull('expenses.deleted_at')
            ->select([
                'expenses.id',
                'expenses.expense_no',
                'expenses.reference_no',
                'expenses.date',
                'expenses.due_date',
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get()
            ->map(function ($row) {
                $grandTotal = (float) $row->subtotal - (float) $row->discount_total + (float) $row->tax_total;
                $paidTotal = (float) $row->paid_total;
                $due = max($grandTotal - $paidTotal, 0);
                $row->grand_total = round($grandTotal, 2);
                $row->paid_total = round($paidTotal, 2);
                $row->due_amount = round($due, 2);

                return $row;
            });

        return response()->json([
            'data' => $rows,
        ]);
    }
}

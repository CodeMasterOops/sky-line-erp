<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\RecurringJournal;
use App\Models\Journal;
use App\Models\JournalItem;
use App\Annotation\Permissions;
use App\Enums\JournalTypeEnum;
use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecurringJournalController extends Controller
{
    /**
     * @Permissions("list_recurring_journal", group="recurring_journal", desc="List Recurring Journals")
     */
    public function index(Request $request)
    {
        $company = auth('admin')->user()->company;

        $journals = RecurringJournal::with(['items.account', 'createdBy'])
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 15));

        return response()->json($journals);
    }

    /**
     * @Permissions("show_recurring_journal", group="recurring_journal", desc="Show Recurring Journal")
     */
    public function show(RecurringJournal $recurringJournal)
    {
        return response()->json([
            'data' => $recurringJournal->load(['items.account', 'createdBy']),
        ]);
    }

    /**
     * @Permissions("create_recurring_journal", group="recurring_journal", desc="Create Recurring Journal")
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly,quarterly,yearly'],
            'next_run_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:next_run_date'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'items.*.dr_amount' => ['required', 'numeric', 'min:0'],
            'items.*.cr_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $company = auth('admin')->user()->company;

        $recurringJournal = DB::transaction(function () use ($validated, $company) {
            $journal = RecurringJournal::create([
                'company_id' => $company->id,
                'name' => $validated['name'],
                'frequency' => $validated['frequency'],
                'next_run_date' => $validated['next_run_date'],
                'end_date' => $validated['end_date'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'is_active' => true,
                'created_by' => auth('admin')->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $journal->items()->create([
                    'account_id' => $item['account_id'],
                    'dr_amount' => $item['dr_amount'],
                    'cr_amount' => $item['cr_amount'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return $journal;
        });

        return response()->json([
            'data' => $recurringJournal->load(['items.account']),
            'message' => 'Recurring journal created successfully.',
        ], 201);
    }

    /**
     * @Permissions("edit_recurring_journal", group="recurring_journal", desc="Update Recurring Journal")
     */
    public function update(Request $request, RecurringJournal $recurringJournal)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly,quarterly,yearly'],
            'next_run_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'items.*.dr_amount' => ['required', 'numeric', 'min:0'],
            'items.*.cr_amount' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $recurringJournal) {
            $recurringJournal->update([
                'name' => $validated['name'],
                'frequency' => $validated['frequency'],
                'next_run_date' => $validated['next_run_date'],
                'end_date' => $validated['end_date'] ?? null,
                'is_active' => $validated['is_active'] ?? $recurringJournal->is_active,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $recurringJournal->items()->delete();
            foreach ($validated['items'] as $item) {
                $recurringJournal->items()->create([
                    'account_id' => $item['account_id'],
                    'dr_amount' => $item['dr_amount'],
                    'cr_amount' => $item['cr_amount'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }
        });

        return response()->json([
            'data' => $recurringJournal->fresh()->load(['items.account']),
            'message' => 'Recurring journal updated successfully.',
        ]);
    }

    /**
     * @Permissions("delete_recurring_journal", group="recurring_journal", desc="Delete Recurring Journal")
     */
    public function destroy(RecurringJournal $recurringJournal)
    {
        $recurringJournal->items()->delete();
        $recurringJournal->delete();

        return response()->json(['message' => 'Recurring journal deleted successfully.']);
    }

    /**
     * @Permissions("create_recurring_journal", group="recurring_journal", desc="Run Recurring Journal Now")
     */
    public function runNow(RecurringJournal $recurringJournal)
    {
        if (! $recurringJournal->is_active) {
            return response()->json(['message' => 'Recurring journal is not active.'], 422);
        }

        $company = auth('admin')->user()->company;

        $journal = DB::transaction(function () use ($recurringJournal, $company) {
            $journal = Journal::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $company->fiscal_year_id,
                'type' => JournalTypeEnum::RECURRING->value,
                'date' => now()->toDateString(),
                'voucher_no' => 'REC-'.now()->format('YmdHis'),
                'remarks' => $recurringJournal->name.': '.$recurringJournal->remarks,
                'status' => StatusEnum::APPROVED->value,
                'create_user_id' => auth('admin')->id(),
                'approve_user_id' => auth('admin')->id(),
                'approved_at' => now(),
            ]);

            foreach ($recurringJournal->items as $item) {
                $journal->journalItems()->create([
                    'account_id' => $item->account_id,
                    'dr_amount' => $item->dr_amount,
                    'cr_amount' => $item->cr_amount,
                    'remarks' => $item->remarks,
                ]);
            }

            $recurringJournal->update(['last_run_at' => now()]);

            return $journal;
        });

        return response()->json([
            'data' => $journal,
            'message' => 'Recurring journal posted successfully.',
        ]);
    }
}

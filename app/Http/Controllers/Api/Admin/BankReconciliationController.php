<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\JournalItem;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\NepalBankStatementParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankReconciliationController extends Controller
{
    /**
     * @Permissions("list_bank_account", group="bank_reconciliation", desc="List Bank Accounts")
     */
    public function bankAccounts(Request $request)
    {
        $company = auth('admin')->user()->company;
        $accounts = BankAccount::with('account')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        return response()->json(['data' => $accounts]);
    }

    /**
     * @Permissions("create_bank_account", group="bank_reconciliation", desc="Create Bank Account")
     */
    public function storeBankAccount(Request $request)
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'branch' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:3'],
        ]);

        $company = auth('admin')->user()->company;
        $bankAccount = BankAccount::create(['company_id' => $company->id] + $validated);

        return response()->json([
            'data' => $bankAccount->load('account'),
            'message' => 'Bank account created successfully.',
        ], 201);
    }

    /**
     * @Permissions("list_bank_statement", group="bank_reconciliation", desc="List Statement Lines")
     */
    public function statementLines(Request $request, BankAccount $bankAccount)
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
        ]);

        $query = BankStatementLine::where('bank_account_id', $bankAccount->id);

        if ($request->input('start_date')) {
            $query->where('transaction_date', '>=', $request->input('start_date'));
        }
        if ($request->input('end_date')) {
            $query->where('transaction_date', '<=', $request->input('end_date'));
        }
        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        $lines = $query->with('journalItem.journal')->orderBy('transaction_date')->orderBy('id')->get();

        $glBalance = $this->getGlBalance($bankAccount, $request->input('end_date'));
        $statementBalance = $lines->sum('credit') - $lines->sum('debit');

        return response()->json([
            'data' => $lines,
            'summary' => [
                'gl_balance' => $glBalance,
                'statement_balance' => round($statementBalance, 2),
                'difference' => round($glBalance - $statementBalance, 2),
                'unmatched_count' => $lines->where('status', 'unmatched')->count(),
            ],
        ]);
    }

    /**
     * @Permissions("create_bank_statement", group="bank_reconciliation", desc="Import Statement Lines")
     */
    public function importLines(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.transaction_date' => ['required', 'date'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.reference' => ['nullable', 'string'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.balance' => ['nullable', 'numeric'],
        ]);

        $created = DB::transaction(function () use ($validated, $bankAccount) {
            $lines = [];
            foreach ($validated['lines'] as $line) {
                $lines[] = BankStatementLine::create([
                    'bank_account_id' => $bankAccount->id,
                    'transaction_date' => $line['transaction_date'],
                    'description' => $line['description'] ?? null,
                    'reference' => $line['reference'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'balance' => $line['balance'] ?? null,
                    'status' => 'unmatched',
                ]);
            }

            return $lines;
        });

        return response()->json([
            'message' => count($created).' lines imported.',
        ], 201);
    }

    /**
     * @Permissions("edit_bank_statement", group="bank_reconciliation", desc="Match Statement Line")
     */
    public function matchLine(Request $request, BankStatementLine $bankStatementLine)
    {
        $validated = $request->validate([
            'journal_item_id' => ['required', 'integer', 'exists:journal_items,id'],
        ]);

        $bankStatementLine->update([
            'journal_item_id' => $validated['journal_item_id'],
            'status' => 'matched',
        ]);

        return response()->json([
            'data' => $bankStatementLine->fresh()->load('journalItem.journal'),
            'message' => 'Line matched successfully.',
        ]);
    }

    /**
     * @Permissions("edit_bank_statement", group="bank_reconciliation", desc="Unmatch Statement Line")
     */
    public function unmatchLine(BankStatementLine $bankStatementLine)
    {
        $bankStatementLine->update([
            'journal_item_id' => null,
            'status' => 'unmatched',
        ]);

        return response()->json([
            'data' => $bankStatementLine->fresh(),
            'message' => 'Line unmatched.',
        ]);
    }

    /**
     * @Permissions("list_bank_statement", group="bank_reconciliation", desc="Auto Match Lines")
     */
    public function autoMatch(BankAccount $bankAccount)
    {
        $companyId = auth('admin')->user()->company_id;
        $unmatchedLines = BankStatementLine::where('bank_account_id', $bankAccount->id)
            ->where('status', 'unmatched')
            ->get();

        $matched = 0;

        foreach ($unmatchedLines as $line) {
            $amount = $line->credit > 0 ? $line->credit : -$line->debit;

            $journalItem = JournalItem::whereHas('journal', function ($q) use ($companyId, $bankAccount, $line) {
                $q->where('company_id', $companyId)
                    ->whereBetween('date', [
                        $line->transaction_date->copy()->subDays(3)->toDateString(),
                        $line->transaction_date->copy()->addDays(3)->toDateString(),
                    ]);
            })
                ->where('account_id', $bankAccount->account_id)
                ->whereNotExists(function ($q) {
                    $q->from('bank_statement_lines')->whereColumn('bank_statement_lines.journal_item_id', 'journal_items.id');
                })
                ->where(function ($q) use ($amount) {
                    if ($amount >= 0) {
                        $q->where('cr_amount', round(abs($amount), 2));
                    } else {
                        $q->where('dr_amount', round(abs($amount), 2));
                    }
                })
                ->first();

            if ($journalItem) {
                $line->update([
                    'journal_item_id' => $journalItem->id,
                    'status' => 'matched',
                ]);
                $matched++;
            }
        }

        return response()->json(['message' => "{$matched} lines auto-matched."]);
    }

    private function getGlBalance(BankAccount $bankAccount, ?string $endDate): float
    {
        $companyId = auth('admin')->user()->company_id;

        $balance = JournalItem::whereHas('journal', function ($q) use ($companyId, $endDate) {
            $q->where('company_id', $companyId);
            if ($endDate) {
                $q->where('date', '<=', $endDate);
            }
        })
            ->where('account_id', $bankAccount->account_id)
            ->selectRaw('SUM(cr_amount - dr_amount) as balance')
            ->value('balance');

        return round((float) ($balance ?? 0), 2);
    }

    /**
     * @Permissions("create_bank_statement", group="bank_reconciliation", desc="Import CSV from Nepal Banks")
     */
    public function importCsv(Request $request, BankAccount $bankAccount, NepalBankStatementParser $parser)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'bank' => 'nullable|string|in:nmb,nabil,himalayan,global_ime,auto',
        ]);

        $csvContent = file_get_contents($request->file('file')->getRealPath());
        $bank       = $request->bank ?? 'auto';

        $parsed = $parser->parse($csvContent, $bank);

        if ($parsed->isEmpty()) {
            return response()->json(['message' => 'No valid rows found in the CSV file.'], 422);
        }

        $created = DB::transaction(function () use ($parsed, $bankAccount) {
            $rows = [];
            foreach ($parsed as $row) {
                $rows[] = BankStatementLine::create([
                    'bank_account_id'  => $bankAccount->id,
                    'transaction_date' => $row['date'],
                    'description'      => $row['description'] ?? null,
                    'reference'        => $row['reference'] ?? null,
                    'debit'            => $row['debit'],
                    'credit'           => $row['credit'],
                    'balance'          => $row['balance'] ?? null,
                    'status'           => 'unmatched',
                ]);
            }
            return $rows;
        });

        return response()->json([
            'message'       => count($created).' rows imported from CSV.',
            'imported_count'=> count($created),
        ]);
    }
}

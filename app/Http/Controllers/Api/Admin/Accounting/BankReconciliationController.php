<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Models\BankMatchingRule;
use App\Models\BankStatementLine;
use App\Models\BankReconciliation;
use Illuminate\Support\Facades\DB;
use App\Models\BankStatementImport;
use App\Http\Controllers\Controller;
use App\Services\NepalBankStatementParser;
use App\Services\Accounting\BankReconciliationService;
use App\Http\Requests\Api\Admin\Accounting\BankAccountRequest;
use App\Http\Requests\Api\Admin\Accounting\CreateBankEntryRequest;
use App\Http\Requests\Api\Admin\Accounting\BankMatchingRuleRequest;
use App\Http\Requests\Api\Admin\Accounting\MatchStatementLineRequest;
use App\Http\Requests\Api\Admin\Accounting\StartReconciliationRequest;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly BankReconciliationService $service) {}

    #[Permissions('list_bank_account', group: 'bank_reconciliation', desc: 'List Bank Accounts')]
    public function bankAccounts(Request $request)
    {
        $accounts = BankAccount::with('account')
            ->where('is_active', true)
            ->get()
            ->map(function (BankAccount $bankAccount) {
                $glBalance = $this->service->glBalance($bankAccount);
                $statementBalance = $this->service->statementBalance($bankAccount);

                return array_merge($bankAccount->toArray(), [
                    'book_balance' => $glBalance,
                    'statement_balance' => $statementBalance,
                    'difference' => round($statementBalance - $glBalance, 2),
                    'unreconciled_count' => $bankAccount->statementLines()->where('status', 'unmatched')->count(),
                ]);
            });

        return response()->json(['data' => $accounts]);
    }

    #[Permissions('create_bank_account', group: 'bank_reconciliation', desc: 'Create Bank Account')]
    public function storeBankAccount(BankAccountRequest $request)
    {
        $bankAccount = BankAccount::create($request->validated());

        return response()->json([
            'data' => $bankAccount->load('account'),
            'message' => 'Bank account created successfully.',
        ], 201);
    }

    #[Permissions('list_bank_statement', group: 'bank_reconciliation', desc: 'List Statement Lines')]
    public function statementLines(Request $request, BankAccount $bankAccount)
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
        ]);

        $query = BankStatementLine::where('bank_account_id', $bankAccount->id);

        if ($request->input('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->input('start_date'));
        }
        if ($request->input('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->input('end_date'));
        }
        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        $lines = $query->with('journalItem.journal')->orderBy('transaction_date')->orderBy('id')->get();

        $glBalance = $this->service->glBalance($bankAccount, $request->input('end_date'));
        $statementBalance = $this->service->statementBalance($bankAccount, $request->input('end_date'));

        return response()->json([
            'data' => $lines,
            'summary' => [
                'gl_balance' => $glBalance,
                'statement_balance' => $statementBalance,
                'difference' => round($statementBalance - $glBalance, 2),
                'unmatched_count' => $lines->where('status', 'unmatched')->count(),
            ],
        ]);
    }

    #[Permissions('create_bank_statement', group: 'bank_reconciliation', desc: 'Import Statement Lines')]
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

        $result = $this->persistStatementLines($bankAccount, $validated['lines'], 'manual');

        return response()->json([
            'message' => "{$result['imported']} lines imported, {$result['skipped']} duplicates skipped.",
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ], 201);
    }

    #[Permissions('create_bank_statement', group: 'bank_reconciliation', desc: 'Import CSV from Nepal Banks')]
    public function importCsv(Request $request, BankAccount $bankAccount, NepalBankStatementParser $parser)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'bank' => 'nullable|string|in:nmb,nabil,himalayan,global_ime,auto',
        ]);

        $csvContent = file_get_contents($request->file('file')->getRealPath());
        $parsed = $parser->parse($csvContent, $request->bank ?? 'auto');

        if ($parsed->isEmpty()) {
            return response()->json(['message' => 'No valid rows found in the CSV file.'], 422);
        }

        $rows = $parsed->map(fn ($row) => [
            'transaction_date' => $row['date'],
            'description' => $row['description'] ?? null,
            'reference' => $row['reference'] ?? null,
            'debit' => $row['debit'],
            'credit' => $row['credit'],
            'balance' => $row['balance'] ?? null,
        ]);

        $result = $this->persistStatementLines(
            $bankAccount,
            $rows,
            'csv',
            $request->file('file')->getClientOriginalName(),
            sha1($csvContent),
        );

        return response()->json([
            'message' => "{$result['imported']} rows imported from CSV, {$result['skipped']} duplicates skipped.",
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);
    }

    #[Permissions('list_bank_statement', group: 'bank_reconciliation', desc: 'Auto Match Lines')]
    public function autoMatch(BankAccount $bankAccount)
    {
        $matched = $this->service->autoMatch($bankAccount);

        return response()->json(['message' => "{$matched} lines auto-matched.", 'matched' => $matched]);
    }

    #[Permissions('list_bank_statement', group: 'bank_reconciliation', desc: 'Apply Matching Rules')]
    public function applyRules(BankAccount $bankAccount)
    {
        $posted = $this->service->applyRules($bankAccount);

        return response()->json(['message' => "{$posted} lines posted by rules.", 'posted' => $posted]);
    }

    #[Permissions('edit_bank_statement', group: 'bank_reconciliation', desc: 'Match Statement Line')]
    public function matchLine(MatchStatementLineRequest $request, BankStatementLine $bankStatementLine)
    {
        $this->assertLineOwned($bankStatementLine);

        $line = $this->service->manualMatch($bankStatementLine, $request->validated()['journal_item_id']);

        return response()->json([
            'data' => $line->load('journalItem.journal'),
            'message' => 'Line matched successfully.',
        ]);
    }

    #[Permissions('edit_bank_statement', group: 'bank_reconciliation', desc: 'Unmatch Statement Line')]
    public function unmatchLine(BankStatementLine $bankStatementLine)
    {
        $this->assertLineOwned($bankStatementLine);

        return response()->json([
            'data' => $this->service->unmatch($bankStatementLine),
            'message' => 'Line unmatched.',
        ]);
    }

    #[Permissions('edit_bank_statement', group: 'bank_reconciliation', desc: 'Create Entry From Line')]
    public function createEntry(CreateBankEntryRequest $request, BankStatementLine $bankStatementLine)
    {
        $this->assertLineOwned($bankStatementLine);

        $line = $this->service->createEntryForLine($bankStatementLine, (int) $request->validated()['contra_account_id']);

        return response()->json([
            'data' => $line->load('journalItem.journal'),
            'message' => 'Entry created and matched.',
        ], 201);
    }

    #[Permissions('edit_bank_statement', group: 'bank_reconciliation', desc: 'Park Line To Suspense')]
    public function parkToSuspense(BankStatementLine $bankStatementLine)
    {
        $this->assertLineOwned($bankStatementLine);

        $line = $this->service->parkToSuspense($bankStatementLine);

        return response()->json([
            'data' => $line->load('journalItem.journal'),
            'message' => 'Line parked to suspense.',
        ], 201);
    }

    #[Permissions('list_bank_statement', group: 'bank_reconciliation', desc: 'List Matching Rules')]
    public function rules(BankAccount $bankAccount)
    {
        $rules = BankMatchingRule::where(function ($q) use ($bankAccount) {
            $q->whereNull('bank_account_id')->orWhere('bank_account_id', $bankAccount->id);
        })->with('targetAccount')->orderBy('priority')->get();

        return response()->json(['data' => $rules]);
    }

    #[Permissions('create_bank_statement', group: 'bank_reconciliation', desc: 'Create Matching Rule')]
    public function storeRule(BankMatchingRuleRequest $request)
    {
        $rule = BankMatchingRule::create($request->validated());

        return response()->json(['data' => $rule->load('targetAccount'), 'message' => 'Rule created.'], 201);
    }

    #[Permissions('list_bank_statement', group: 'bank_reconciliation', desc: 'Reconciliation History')]
    public function reconciliations(BankAccount $bankAccount)
    {
        $history = BankReconciliation::where('bank_account_id', $bankAccount->id)
            ->with('reconciledBy:id,name')
            ->orderByDesc('period_end')
            ->get();

        return response()->json(['data' => $history]);
    }

    #[Permissions('create_bank_statement', group: 'bank_reconciliation', desc: 'Start Reconciliation')]
    public function startReconciliation(StartReconciliationRequest $request, BankAccount $bankAccount)
    {
        $reconciliation = $this->service->startReconciliation($bankAccount, $request->validated());

        return response()->json(['data' => $reconciliation, 'message' => 'Reconciliation started.'], 201);
    }

    #[Permissions('edit_bank_statement', group: 'bank_reconciliation', desc: 'Complete Reconciliation')]
    public function completeReconciliation(BankReconciliation $bankReconciliation)
    {
        $reconciliation = $this->service->completeReconciliation($bankReconciliation);

        return response()->json(['data' => $reconciliation, 'message' => 'Reconciliation completed and locked.']);
    }

    #[Permissions('edit_bank_statement', group: 'bank_reconciliation', desc: 'Reopen Reconciliation')]
    public function reopenReconciliation(BankReconciliation $bankReconciliation)
    {
        $reconciliation = $this->service->reopenReconciliation($bankReconciliation);

        return response()->json(['data' => $reconciliation, 'message' => 'Reconciliation reopened.']);
    }

    /**
     * The route-model-bound line must belong to the current tenant's bank account.
     */
    private function assertLineOwned(BankStatementLine $line): void
    {
        $companyId = auth('admin')->user()->company_id;
        $bankAccount = $line->bankAccount()->withoutGlobalScopes()->first();
        abort_if($bankAccount === null || $bankAccount->company_id !== $companyId, 403);
    }

    /**
     * Inserts statement lines for a bank account, skipping duplicates by hash, and
     * records the import batch. Dedup is set-based: existing hashes are loaded once.
     *
     * @param  iterable<array{transaction_date: mixed, description?: ?string, reference?: ?string, debit: float|int|string, credit: float|int|string, balance?: float|int|string|null}>  $rows
     * @return array{imported: int, skipped: int, import_id: int}
     */
    private function persistStatementLines(
        BankAccount $bankAccount,
        iterable $rows,
        string $source,
        ?string $fileName = null,
        ?string $fileHash = null,
    ): array {
        return DB::transaction(function () use ($bankAccount, $rows, $source, $fileName, $fileHash) {
            $rows = collect($rows);

            $import = BankStatementImport::create([
                'company_id' => $bankAccount->company_id,
                'bank_account_id' => $bankAccount->id,
                'file_name' => $fileName,
                'file_hash' => $fileHash,
                'source' => $source,
                'row_count' => $rows->count(),
                'status' => 'completed',
                'created_by' => auth('admin')->id(),
            ]);

            $seenHashes = BankStatementLine::where('bank_account_id', $bankAccount->id)
                ->pluck('hash')
                ->filter()
                ->flip();

            $imported = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $hash = BankStatementLine::makeHash(
                    $bankAccount->id,
                    $row['transaction_date'],
                    $row['debit'] ?? 0,
                    $row['credit'] ?? 0,
                    $row['reference'] ?? null,
                    $row['balance'] ?? null,
                );

                if ($seenHashes->has($hash)) {
                    $skipped++;

                    continue;
                }

                BankStatementLine::create([
                    'bank_account_id' => $bankAccount->id,
                    'import_id' => $import->id,
                    'transaction_date' => $row['transaction_date'],
                    'description' => $row['description'] ?? null,
                    'reference' => $row['reference'] ?? null,
                    'debit' => $row['debit'] ?? 0,
                    'credit' => $row['credit'] ?? 0,
                    'balance' => $row['balance'] ?? null,
                    'status' => 'unmatched',
                    'hash' => $hash,
                ]);

                $seenHashes->put($hash, true);
                $imported++;
            }

            $import->update(['imported_count' => $imported, 'skipped_count' => $skipped]);

            return ['imported' => $imported, 'skipped' => $skipped, 'import_id' => $import->id];
        });
    }
}

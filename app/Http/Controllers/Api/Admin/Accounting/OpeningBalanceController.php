<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Bill;
use App\Models\Party;
use App\Models\Invoice;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Enums\PartyTypeEnum;
use Illuminate\Http\Request;
use App\Enums\JournalTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Rules\WithinActiveFiscalYear;
use App\Services\DocumentNumberGenerator;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalBalanceGuard;
use App\Services\Accounting\OpeningPartyBalanceService;

class OpeningBalanceController extends Controller
{
    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
        private readonly OpeningPartyBalanceService $openingPartyBalanceService,
    ) {}

    #[Permissions('list_journal_voucher', group: 'journal_voucher', desc: 'List Opening Balance Entries')]
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $company = auth('admin')->user()->company;

        $journals = Journal::where('company_id', $company->id)
            ->where('type', JournalTypeEnum::OPENING_BALANCE->value)
            ->with(['journalItems.account', 'fiscalYear'])
            ->latest('date')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $journals->items(),
            'meta' => [
                'current_page' => $journals->currentPage(),
                'last_page' => $journals->lastPage(),
                'per_page' => $journals->perPage(),
                'total' => $journals->total(),
            ],
        ]);
    }

    #[Permissions('create_journal_voucher', group: 'journal_voucher', desc: 'Post Opening Balance')]
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'date' => ['required', 'date', new WithinActiveFiscalYear],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'items.*.dr_amount' => ['required', 'numeric', 'min:0'],
            'items.*.cr_amount' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $items = collect($validated['items'])->filter(
            fn ($i) => ((float) $i['dr_amount']) > 0 || ((float) $i['cr_amount']) > 0
        );

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'At least one line must have a non-zero Debit or Credit amount.',
            ]);
        }

        $totalDr = $items->sum(fn ($i) => (float) $i['dr_amount']);
        $totalCr = $items->sum(fn ($i) => (float) $i['cr_amount']);

        if (abs($totalDr - $totalCr) > JournalBalanceGuard::TOLERANCE) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'Total Debit (%.2f) must equal Total Credit (%.2f). Difference: %.2f',
                    $totalDr,
                    $totalCr,
                    abs($totalDr - $totalCr),
                ),
            ]);
        }

        $user = auth('admin')->user();
        $company = $user->company;
        $fiscalYearId = $validated['fiscal_year_id'] ?? $company->fiscal_year_id;

        $exists = Journal::where('company_id', $company->id)
            ->where('type', JournalTypeEnum::OPENING_BALANCE->value)
            ->where('fiscal_year_id', $fiscalYearId)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => 'An opening balance entry already exists for this fiscal year. Void the existing entry before posting a new one.',
            ]);
        }

        $journal = DB::transaction(function () use ($validated, $items, $user, $company, $fiscalYearId) {
            $voucherNo = $this->documentNumberGenerator->journalVoucher(
                JournalTypeEnum::OPENING_BALANCE,
                'OB-',
                $fiscalYearId,
                $company->fiscalYear?->year_code,
            );

            $journal = Journal::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYearId,
                'type' => JournalTypeEnum::OPENING_BALANCE->value,
                'date' => $validated['date'],
                'voucher_no' => $voucherNo,
                'remarks' => $validated['remarks'] ?? 'Opening Balance Entry',
                'status' => StatusEnum::APPROVED->value,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
            ]);

            $journal->journalItems()->createMany(
                $items->map(fn ($i) => [
                    'account_id' => $i['account_id'],
                    'dr_amount' => (float) $i['dr_amount'],
                    'cr_amount' => (float) $i['cr_amount'],
                    'remarks' => $i['remarks'] ?? null,
                ])->values()->all()
            );

            return $journal;
        });

        return response()->json([
            'data' => $journal->load(['journalItems.account', 'fiscalYear']),
            'message' => 'Opening balance posted successfully.',
        ], 201);
    }

    #[Permissions('list_journal_voucher', group: 'journal_voucher', desc: 'List Opening Balance Parties')]
    public function parties(): \Illuminate\Http\JsonResponse
    {
        $customerOpeningIds = Invoice::query()->where('is_opening', true)->pluck('party_id');
        $supplierOpeningIds = Bill::query()->where('is_opening', true)->pluck('party_id');

        return response()->json([
            'data' => [
                'customers' => $this->partyList(PartyTypeEnum::CUSTOMER, $customerOpeningIds->all()),
                'suppliers' => $this->partyList(PartyTypeEnum::SUPPLIER, $supplierOpeningIds->all()),
            ],
        ]);
    }

    #[Permissions('create_journal_voucher', group: 'journal_voucher', desc: 'Post Customer Opening Balance')]
    public function storeCustomers(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $this->validatePartyLines($request);

        $documents = $this->openingPartyBalanceService->postCustomers(
            $validated['lines'],
            $validated['date'],
            $validated['fiscal_year_id'] ?? null,
        );

        return response()->json([
            'message' => count($documents).' customer opening balance(s) posted successfully.',
        ], 201);
    }

    #[Permissions('create_journal_voucher', group: 'journal_voucher', desc: 'Post Supplier Opening Balance')]
    public function storeSuppliers(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $this->validatePartyLines($request);

        $documents = $this->openingPartyBalanceService->postSuppliers(
            $validated['lines'],
            $validated['date'],
            $validated['fiscal_year_id'] ?? null,
        );

        return response()->json([
            'message' => count($documents).' supplier opening balance(s) posted successfully.',
        ], 201);
    }

    /**
     * @return array{date: string, fiscal_year_id: int|null, lines: array<int, array{party_id: int, amount: float, remarks: string|null}>}
     */
    private function validatePartyLines(Request $request): array
    {
        return $request->validate([
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'date' => ['required', 'date', new WithinActiveFiscalYear],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.party_id' => ['required', 'integer', 'exists:parties,id'],
            'lines.*.amount' => ['required', 'numeric', 'min:0'],
            'lines.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @param  array<int, int>  $withOpeningIds
     * @return array<int, array{id: string, name: string, code: string|null, has_opening: bool}>
     */
    private function partyList(PartyTypeEnum $type, array $withOpeningIds): array
    {
        $withOpening = array_flip($withOpeningIds);

        return Party::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Party $party) => [
                'id' => (string) $party->id,
                'name' => $party->name,
                'code' => $party->code,
                'has_opening' => isset($withOpening[$party->id]),
            ])
            ->all();
    }
}

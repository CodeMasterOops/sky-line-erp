<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Journal;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Enums\JournalTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\DocumentNumberGenerator;
use App\Services\Accounting\PeriodLockGuard;
use App\Http\Resources\Admin\Accounting\JournalVoucherResource;
use App\Http\Requests\Api\Admin\Accounting\JournalVoucherRequest;

class JournalVoucherController extends Controller
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
        private PeriodLockGuard $periodGuard,
    ) {}

    /**
     * @Permissions("list_journal_voucher", group="journal_voucher", desc="List Journal Voucher")
     */
    public function index(Request $request)
    {
        $filters = $request->all();

        $journals = Journal::filter($filters)
            ->where('type', JournalTypeEnum::JOURNAL_VOUCHER->value)
            ->latest('date')
            ->paginate($request->limit ?? 25);

        return JournalVoucherResource::collection($journals);
    }

    /**
     * @Permissions("create_journal_voucher", group="journal_voucher", desc="Create Journal Voucher")
     */
    public function store(JournalVoucherRequest $request)
    {
        $formData = $request->validated();

        if (collect($formData['items'])->sum('dr_amount') !== collect($formData['items'])->sum('cr_amount')) {
            return response()->json([
                'message' => 'Dr Amount & Cr Amount must be equal',
            ], 400);
        }

        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        if ($status === StatusEnum::APPROVED->value) {
            $this->periodGuard->assertPostable($setting->id, $fiscalYearId, $formData['date']);
        }

        $formData['fiscal_year_id'] = $fiscalYearId;
        $formData['type'] = JournalTypeEnum::JOURNAL_VOUCHER->value;
        $formData['create_user_id'] = $user->id;
        $formData['approve_user_id'] = $status === StatusEnum::APPROVED->value ? $user->id : null;
        $formData['approved_at'] = $status === StatusEnum::APPROVED->value ? now() : null;
        $formData['status'] = $status;

        $journal = DB::transaction(function () use ($formData, $fiscalYearId, $setting) {
            // See InvoiceService for the lock-inside-transaction concurrency note.
            $formData['voucher_no'] = $this->documentNumberGenerator->journalVoucher(
                JournalTypeEnum::JOURNAL_VOUCHER,
                'JV-',
                $fiscalYearId,
                $setting->fiscalYear?->year_code,
            );
            $journal = Journal::create($formData);

            $journal->journalItems()->createMany($formData['items']);

            return $journal;
        });

        $journal->load(['journalItems.account']);

        return response()->json([
            'data' => JournalVoucherResource::make($journal),
            'message' => 'Journal Voucher Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_journal_voucher", group="journal_voucher", desc="Show Journal Voucher")
     */
    public function show(Journal $journalVoucher)
    {
        $this->ensureJournalVoucher($journalVoucher);

        $journalVoucher->load(['journalItems.account']);

        return JournalVoucherResource::make($journalVoucher);
    }

    /**
     * @Permissions("edit_journal_voucher", group="journal_voucher", desc="Edit Journal Voucher")
     */
    public function update(JournalVoucherRequest $request, Journal $journalVoucher)
    {
        $this->ensureJournalVoucher($journalVoucher);

        if ($journalVoucher->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved journal vouchers cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();

        $journalVoucher = DB::transaction(function () use ($journalVoucher, $formData) {
            $journalVoucher->update($formData);

            $journalVoucher->journalItems()->delete();
            $journalVoucher->journalItems()->createMany($formData['items']);

            return $journalVoucher;
        });

        $journalVoucher->load(['journalItems.account']);

        return response()->json([
            'data' => JournalVoucherResource::make($journalVoucher),
            'message' => 'Journal Voucher Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_journal_voucher", group="journal_voucher", desc="Delete Journal Voucher")
     */
    public function destroy(Journal $journalVoucher)
    {
        $this->ensureJournalVoucher($journalVoucher);

        if ($journalVoucher->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved journal vouchers cannot be deleted. Please void the entry instead.',
            ], 422);
        }

        $journalVoucher->journalItems()->delete();
        $journalVoucher->delete();

        return response()->json([
            'message' => 'Journal Voucher Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_journal_voucher", group="journal_voucher", desc="Approve Journal Voucher")
     */
    public function approve(Journal $journalVoucher)
    {
        $this->ensureJournalVoucher($journalVoucher);

        if ($journalVoucher->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => JournalVoucherResource::make($journalVoucher->load(['journalItems.account'])),
                'message' => 'Journal Voucher Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        $this->periodGuard->assertPostable(
            $journalVoucher->company_id,
            $journalVoucher->fiscal_year_id,
            $journalVoucher->date,
        );

        $journalVoucher->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $journalVoucher->load(['journalItems.account']);

        return response()->json([
            'data' => JournalVoucherResource::make($journalVoucher),
            'message' => 'Journal Voucher Approved Successfully',
        ]);
    }

    private function ensureJournalVoucher(Journal $journal): void
    {
        if ($journal->type !== JournalTypeEnum::JOURNAL_VOUCHER) {
            abort(404);
        }
    }
}

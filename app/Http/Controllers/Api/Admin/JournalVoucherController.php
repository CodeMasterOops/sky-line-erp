<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Journal;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Enums\JournalTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\JournalVoucherResource;
use App\Http\Requests\Api\Admin\JournalVoucherRequest;

class JournalVoucherController extends Controller
{
    /**
     * @Permissions("list_journal_voucher", group="journal_voucher", desc="List Journal Voucher")
     */
    public function index(Request $request)
    {
        $query = Journal::where('type', JournalTypeEnum::JOURNAL_VOUCHER->value)
            ->orderByDesc('date');

        if (! empty($request->search)) {
            $key = '%'.trim($request->search).'%';
            $query->where('reference_no', 'like', $key);
        }

        $journals = $query->paginate($request->limit ?? 25);

        return JournalVoucherResource::collection($journals);
    }

    /**
     * @Permissions("create_journal_voucher", group="journal_voucher", desc="Create Journal Voucher")
     */
    public function store(JournalVoucherRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $fiscalYearId = $user->company?->fiscal_year_id;

        $journal = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId) {
            $journal = Journal::create([
                'fiscal_year_id' => $fiscalYearId,
                'type' => JournalTypeEnum::JOURNAL_VOUCHER->value,
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'account_id' => $item['account_id'],
                    'dr_amount' => $item['dr_amount'] ?? 0,
                    'cr_amount' => $item['cr_amount'] ?? 0,
                    'remarks' => $item['remarks'] ?? null,
                ];
            })->all();

            $journal->journalItems()->createMany($items);

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
            $journalVoucher->update([
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $journalVoucher->journalItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'account_id' => $item['account_id'],
                    'dr_amount' => $item['dr_amount'] ?? 0,
                    'cr_amount' => $item['cr_amount'] ?? 0,
                    'remarks' => $item['remarks'] ?? null,
                ];
            })->all();

            $journalVoucher->journalItems()->createMany($items);

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

<?php

namespace App\Services\Accounting;

use RuntimeException;
use App\Models\Journal;
use App\Models\JournalItem;

/**
 * Hard balance check for general-ledger journals: sum(DR) must equal sum(CR)
 * for every posted journal (within a small rounding tolerance). Called inside
 * each posting transaction so a buggy posting path that produces an unbalanced
 * journal rolls back instead of corrupting the ledger.
 *
 * Scope-free so it sees the items just written regardless of the request's
 * tenant context.
 */
class JournalBalanceGuard
{
    /**
     * Permitted DR/CR delta (in account currency) — covers per-line rounding
     * accumulated across a journal with many items.
     */
    public const TOLERANCE = 0.01;

    /**
     * Whether |DR - CR| exceeds the per-journal balance tolerance. Uses a small
     * float epsilon to make the comparison robust against IEEE-754 rounding.
     */
    public static function exceedsTolerance(float $delta): bool
    {
        return abs($delta) > self::TOLERANCE + 0.0001;
    }

    /**
     * @throws RuntimeException when the journal does not balance.
     */
    public function assertBalanced(Journal $journal): void
    {
        $sums = JournalItem::withoutGlobalScopes()
            ->where('journal_id', $journal->id)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(dr_amount), 0) AS dr, COALESCE(SUM(cr_amount), 0) AS cr')
            ->first();

        $dr = round((float) ($sums->dr ?? 0), 2);
        $cr = round((float) ($sums->cr ?? 0), 2);

        if (self::exceedsTolerance($dr - $cr)) {
            throw new RuntimeException(sprintf(
                'Journal %s is not balanced: DR %.2f vs CR %.2f (delta %.2f). Posting aborted.',
                $journal->voucher_no ?: ('#'.$journal->id),
                $dr,
                $cr,
                $dr - $cr,
            ));
        }
    }
}

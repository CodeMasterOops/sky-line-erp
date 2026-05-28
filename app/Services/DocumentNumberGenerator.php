<?php

namespace App\Services;

use App\Models\Journal;
use App\Enums\JournalTypeEnum;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

/**
 * Generates the next sequential document number for invoices, bills, vouchers
 * etc. Concurrency note: the classic `count() + 1` pattern races between
 * concurrent transactions. To make it safe, each method first acquires a
 * `SELECT … FOR UPDATE` lock on the relevant scope row (the fiscal year or the
 * company). The lock holds until the calling transaction commits, so two
 * concurrent transactions trying to generate the same kind of number for the
 * same scope are serialised and cannot read the same `count()`.
 *
 * Callers must run the generator inside their `DB::transaction(...)` for the
 * lock to span the subsequent insert. Called outside a transaction it is a
 * harmless no-op (no worse than the pre-existing behaviour).
 */
class DocumentNumberGenerator
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $scopes
     */
    public function fiscalYear(
        string $modelClass,
        string $prefix,
        ?int $fiscalYearId,
        ?string $yearCode,
        array $scopes = [],
    ): string {
        $this->lockFiscalYear($fiscalYearId);

        $query = $modelClass::query()
            ->where('fiscal_year_id', $fiscalYearId);

        foreach ($scopes as $scopeColumn => $scopeValue) {
            $query->where($scopeColumn, $scopeValue);
        }

        $count = $query->withTrashed()->count();
        $suffix = $yearCode ? '/'.$yearCode : '';

        return $prefix.($count + 1).$suffix;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function companyPadded(
        string $modelClass,
        string $prefix,
        int $companyId,
        int $padding = 5,
    ): string {
        $this->lockCompany($companyId);

        $count = $modelClass::query()
            ->where('company_id', $companyId)
            ->withTrashed()
            ->count();

        return $prefix.str_pad((string) ($count + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function productionOrder(int $companyId): string
    {
        $this->lockCompany($companyId);

        $count = ProductionOrder::query()
            ->where('company_id', $companyId)
            ->withTrashed()
            ->count();

        return 'PO-'.date('ymd').'-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    public function journalVoucher(
        JournalTypeEnum $type,
        string $prefix,
        int $fiscalYearId,
        ?string $yearCode,
    ): string {
        $this->lockFiscalYear($fiscalYearId);

        $count = Journal::query()
            ->where('type', $type->value)
            ->where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        return $prefix.($count + 1).'/'.($yearCode ?? '');
    }

    /**
     * Acquire a row-level FOR UPDATE lock on the fiscal year scope row so any
     * concurrent transaction generating a number for the same fiscal year waits
     * until ours commits. SQLite ignores the lock (driver limitation); on
     * MySQL/Postgres it serialises correctly.
     */
    protected function lockFiscalYear(?int $fiscalYearId): void
    {
        if (! $fiscalYearId || DB::transactionLevel() === 0) {
            return;
        }

        DB::table('fiscal_years')
            ->where('id', $fiscalYearId)
            ->lockForUpdate()
            ->first();
    }

    protected function lockCompany(int $companyId): void
    {
        if ($companyId <= 0 || DB::transactionLevel() === 0) {
            return;
        }

        DB::table('companies')
            ->where('id', $companyId)
            ->lockForUpdate()
            ->first();
    }
}

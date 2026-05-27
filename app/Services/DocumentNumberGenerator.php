<?php

namespace App\Services;

use App\Models\Journal;
use App\Enums\JournalTypeEnum;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Model;

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
        $count = $modelClass::query()
            ->where('company_id', $companyId)
            ->withTrashed()
            ->count();

        return $prefix.str_pad((string) ($count + 1), $padding, '0', STR_PAD_LEFT);
    }

    public function productionOrder(int $companyId): string
    {
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
        $count = Journal::query()
            ->where('type', $type->value)
            ->where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        return $prefix.($count + 1).'/'.($yearCode ?? '');
    }
}

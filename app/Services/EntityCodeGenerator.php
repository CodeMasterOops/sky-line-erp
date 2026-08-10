<?php

namespace App\Services;

use App\Enums\EntityCodeType;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class EntityCodeGenerator
{
    public function generateForType(EntityCodeType $type, int $companyId): string
    {
        return $this->generate(
            $type->modelClass(),
            $companyId,
            $type->prefix(),
            $type->column(),
            $type->padding(),
            $type->scopes(),
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $scopes
     */
    public function generate(
        string $modelClass,
        int $companyId,
        string $prefix,
        string $column = 'code',
        int $padding = 4,
        array $scopes = [],
    ): string {
        $this->lockCompany($companyId);

        // Entity codes are unique per COMPANY, so the count that produces the
        // next one must span the whole company. Leaving the branch scope on
        // would restart numbering in every branch and collide on the company
        // -wide unique index the moment a second branch exists. Global scopes
        // are stripped and company_id re-applied explicitly — the documented
        // safe pattern (docs/branch-isolation-withoutglobalscope-register.md).
        $query = $modelClass::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId);

        foreach ($scopes as $scopeColumn => $scopeValue) {
            $query->where($scopeColumn, $scopeValue);
        }

        $count = $query->withTrashed()->count();

        return $prefix.str_pad((string) ($count + 1), $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Acquire a row-level FOR UPDATE lock on the company row so concurrent
     * code-generation calls for the same company are serialised. Callers must
     * run inside DB::transaction() for the lock to be effective.
     */
    private function lockCompany(int $companyId): void
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

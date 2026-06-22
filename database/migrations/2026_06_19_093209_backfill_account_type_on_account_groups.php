<?php

use App\Models\AccountGroup;
use Illuminate\Database\Migrations\Migration;

/**
 * Classifies every account group with its account_type by walking each company's
 * group tree from the root. Root type is resolved by code (with a name fallback)
 * and inherited by all descendants. Required for Balance Sheet, P&L, cash-flow and
 * year-end closing, which filter groups by account_type. Only fills null values.
 */
return new class extends Migration
{
    /** @var array<string, string> root group code => account_type */
    private array $codeMap = [
        'ASS' => 'asset',
        'LIA' => 'liability',
        'EQU' => 'equity',
        'INC' => 'income',
        'EXP' => 'expense',
    ];

    /** @var array<string, string> lowercased root group name => account_type */
    private array $nameMap = [
        'assets' => 'asset',
        'liabilities' => 'liability',
        'equity' => 'equity',
        'income' => 'income',
        'incomes' => 'income',
        'revenue' => 'income',
        'expenses' => 'expense',
        'expense' => 'expense',
        'expenditure' => 'expense',
    ];

    public function up(): void
    {
        $groups = AccountGroup::withoutGlobalScopes()->get(['id', 'parent_id', 'name', 'code', 'account_type', 'company_id']);
        $byParent = $groups->groupBy('parent_id');

        $roots = $groups->whereNull('parent_id');

        foreach ($roots as $root) {
            $type = $this->resolveRootType($root);
            if ($type === null) {
                continue;
            }

            $this->assignTypeRecursively($root, $type, $byParent);
        }
    }

    public function down(): void
    {
        // No-op: account_type values are also set by the COA seeder, so reversing
        // this backfill would corrupt newly provisioned companies.
    }

    private function resolveRootType(AccountGroup $root): ?string
    {
        if ($root->account_type !== null) {
            return $root->account_type->value;
        }

        return $this->codeMap[$root->code] ?? $this->nameMap[strtolower((string) $root->name)] ?? null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, AccountGroup>>  $byParent
     */
    private function assignTypeRecursively(AccountGroup $group, string $type, $byParent): void
    {
        if ($group->account_type === null) {
            $group->forceFill(['account_type' => $type])->saveQuietly();
        }

        foreach ($byParent->get($group->id, collect()) as $child) {
            $this->assignTypeRecursively($child, $type, $byParent);
        }
    }
};

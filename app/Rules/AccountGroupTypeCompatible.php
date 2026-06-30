<?php

namespace App\Rules;

use Closure;
use App\Models\AccountGroup;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects an account_group_id when the request also carries a `category` that
 * maps to a known account type and conflicts with the group's resolved type.
 * For example, "income" category under an "Asset" group is rejected.
 */
class AccountGroupTypeCompatible implements ValidationRule
{
    public function __construct(private readonly ?string $category) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value || ! $this->category) {
            return;
        }

        $categoryType = AccountGroupTypeEnum::tryFrom(strtolower(trim($this->category)));
        if (! $categoryType) {
            return;
        }

        $group = AccountGroup::find($value);
        if (! $group) {
            return;
        }

        $groupType = $group->resolvedAccountType();
        if (! $groupType) {
            return;
        }

        if ($categoryType !== $groupType) {
            $fail("The account category '{$this->category}' conflicts with the account group type '{$groupType->value}'.");
        }
    }
}

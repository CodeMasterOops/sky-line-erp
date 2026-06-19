<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\AccountGroupTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountGroup extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'code',
        'description',
        'is_active',
        'account_type',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountGroupTypeEnum::class,
        ];
    }

    /**
     * The group's account_type, falling back to a code/name-based inference for
     * legacy groups that were never explicitly classified. Used by the financial
     * statements so a missing account_type never silently drops a root group.
     */
    public function resolvedAccountType(): ?AccountGroupTypeEnum
    {
        return $this->account_type ?? AccountGroupTypeEnum::infer($this->code, $this->name);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with(['childrenRecursive', 'accounts']);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}

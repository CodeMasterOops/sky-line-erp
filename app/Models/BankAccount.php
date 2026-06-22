<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'account_id',
        'bank_name',
        'account_number',
        'branch',
        'currency',
        'is_active',
        'opening_balance',
        'opening_balance_date',
        'last_reconciled_at',
        'last_reconciled_balance',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opening_balance' => 'float',
            'opening_balance_date' => 'date:Y-m-d',
            'last_reconciled_at' => 'datetime',
            'last_reconciled_balance' => 'float',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function matchingRules(): HasMany
    {
        return $this->hasMany(BankMatchingRule::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(BankStatementImport::class);
    }
}

<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliation extends Model
{
    use BranchTenant;
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'bank_account_id',
        'period_start',
        'period_end',
        'statement_opening_balance',
        'statement_closing_balance',
        'gl_balance',
        'difference',
        'status',
        'reconciled_by',
        'reconciled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'statement_opening_balance' => 'float',
            'statement_closing_balance' => 'float',
            'gl_balance' => 'float',
            'difference' => 'float',
            'reconciled_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'reconciliation_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}

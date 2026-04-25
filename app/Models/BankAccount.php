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
        'account_id',
        'bank_name',
        'account_number',
        'branch',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}

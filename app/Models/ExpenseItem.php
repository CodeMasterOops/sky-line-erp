<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_id',
        'account_id',
        'amount',
        'tax_id',
        'tax_amount',
        'discount_amount',
    ];

    protected $casts = [
        'expense_id' => 'integer',
        'account_id' => 'integer',
        'amount' => 'float',
        'tax_amount' => 'float',
        'discount_amount' => 'float',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}

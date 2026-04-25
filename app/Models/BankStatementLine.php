<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'description',
        'reference',
        'debit',
        'credit',
        'balance',
        'status',
        'journal_item_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit' => 'float',
        'credit' => 'float',
        'balance' => 'float',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalItem(): BelongsTo
    {
        return $this->belongsTo(JournalItem::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringJournalItem extends Model
{
    protected $fillable = [
        'recurring_journal_id',
        'account_id',
        'dr_amount',
        'cr_amount',
        'remarks',
    ];

    protected $casts = [
        'dr_amount' => 'float',
        'cr_amount' => 'float',
    ];

    public function recurringJournal(): BelongsTo
    {
        return $this->belongsTo(RecurringJournal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

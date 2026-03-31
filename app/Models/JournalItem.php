<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'journal_id',
        'account_id',
        'dr_amount',
        'cr_amount',
        'remarks',
    ];

    protected $casts = [
        'journal_id' => 'integer',
        'account_id' => 'integer',
        'dr_amount' => 'float',
        'cr_amount' => 'float',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

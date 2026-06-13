<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceAdjustment extends Model
{
    protected $fillable = [
        'advance_receipt_id',
        'invoice_id',
        'amount',
        'adjusted_at',
        'journal_id',
        'create_user_id',
    ];

    public function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'adjusted_at' => 'datetime',
        ];
    }

    public function advanceReceipt(): BelongsTo
    {
        return $this->belongsTo(AdvanceReceipt::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function createUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptAllocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'receipt_id',
        'invoice_id',
        'amount',
    ];

    protected $casts = [
        'receipt_id' => 'integer',
        'invoice_id' => 'integer',
        'amount' => 'float',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_id',
        'payable_type',
        'payable_id',
        'amount',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'payable_id' => 'integer',
        'amount' => 'float',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}

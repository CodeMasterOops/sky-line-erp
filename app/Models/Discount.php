<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Discount extends Model
{
    protected $fillable = [
        'discountable_type',
        'discountable_id',
        'type',
        'value',
        'amount',
    ];

    protected $casts = [
        'value' => 'float',
        'amount' => 'float',
    ];

    public function discountable(): MorphTo
    {
        return $this->morphTo();
    }
}

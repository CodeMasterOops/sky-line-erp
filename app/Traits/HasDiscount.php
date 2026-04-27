<?php

namespace App\Traits;

use App\Models\Discount;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasDiscount
{
    public function discount(): MorphOne
    {
        return $this->morphOne(Discount::class, 'discountable');
    }

    public function saveDiscount(string $type, ?float $value, float $amount = 0): void
    {
        $this->discount()->updateOrCreate(
            [
                'discountable_type' => static::class,
                'discountable_id' => $this->id,
            ],
            [
                'type' => $type,
                'value' => $value,
                'amount' => $amount,
            ]
        );
    }
}

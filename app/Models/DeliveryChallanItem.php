<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChallanItem extends Model
{
    protected $fillable = [
        'delivery_challan_id',
        'product_variant_id',
        'unit_id',
        'quantity',
        'rate',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'float',
        'rate' => 'float',
    ];

    public function deliveryChallan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}

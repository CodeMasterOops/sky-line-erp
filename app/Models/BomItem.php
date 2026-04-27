<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    protected $fillable = [
        'bom_id',
        'product_variant_id',
        'quantity',
        'unit_id',
        'item_type',
        'wastage_pct',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'float',
        'wastage_pct' => 'float',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Effective quantity including wastage allowance */
    public function getEffectiveQtyAttribute(): float
    {
        return $this->quantity * (1 + $this->wastage_pct / 100);
    }
}

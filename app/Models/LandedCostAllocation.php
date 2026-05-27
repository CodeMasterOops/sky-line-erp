<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostAllocation extends Model
{
    protected $fillable = [
        'landed_cost_id',
        'bill_item_id',
        'grn_item_id',
        'stock_layer_id',
        'allocated_amount',
        'allocated_unit_cost',
    ];

    protected $casts = [
        'allocated_amount' => 'float',
        'allocated_unit_cost' => 'float',
    ];

    public function landedCost(): BelongsTo
    {
        return $this->belongsTo(LandedCost::class);
    }

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class);
    }

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GrnItem::class);
    }

    public function stockLayer(): BelongsTo
    {
        return $this->belongsTo(StockLayer::class);
    }
}

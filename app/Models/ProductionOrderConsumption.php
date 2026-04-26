<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderConsumption extends Model
{
    protected $fillable = [
        'production_order_id',
        'product_variant_id',
        'warehouse_id',
        'batch_id',
        'required_qty',
        'consumed_qty',
        'unit_id',
        'unit_cost',
    ];

    protected $casts = [
        'required_qty' => 'float',
        'consumed_qty' => 'float',
        'unit_cost'    => 'float',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrnItem extends Model
{
    protected $fillable = [
        'goods_received_note_id',
        'product_variant_id',
        'purchase_order_item_id',
        'unit_id',
        'ordered_qty',
        'received_qty',
        'billed_qty',
        'unit_cost',
        'batch_no',
        'expiry_date',
        'batch_id',
        'serial_nos',
    ];

    protected $casts = [
        'ordered_qty' => 'float',
        'received_qty' => 'float',
        'billed_qty' => 'float',
        'unit_cost' => 'float',
        'expiry_date' => 'date',
        'serial_nos' => 'array',
    ];

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function landedCostAllocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    public function stockLayers(): HasMany
    {
        return $this->hasMany(StockLayer::class, 'source_grn_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}

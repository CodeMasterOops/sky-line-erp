<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'unit_cost',
        'batch_no',
        'expiry_date',
    ];

    protected $casts = [
        'ordered_qty' => 'float',
        'received_qty' => 'float',
        'unit_cost' => 'float',
        'expiry_date' => 'date',
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
}

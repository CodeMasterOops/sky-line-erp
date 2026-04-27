<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'purchase_order_id',
        'product_variant_id',
        'quantity',
        'unit_id',
        'rate',
        'line_discount_type',
        'line_discount_value',
        'tax_id',
        'tax_amount',
        'discount_amount',
    ];

    protected $casts = [
        'purchase_order_id' => 'integer',
        'product_variant_id' => 'integer',
        'unit_id' => 'integer',
        'tax_id' => 'integer',
        'quantity' => 'integer',
        'rate' => 'float',
        'line_discount_value' => 'float',
        'tax_amount' => 'float',
        'discount_amount' => 'float',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}

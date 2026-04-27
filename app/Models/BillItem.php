<?php

namespace App\Models;

use App\Enums\TaxLineTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'bill_id',
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'unit_id',
        'rate',
        'tax_id',
        'tax_amount',
        'discount_amount',
        'tax_line_type',
    ];

    protected $casts = [
        'bill_id' => 'integer',
        'product_variant_id' => 'integer',
        'warehouse_id' => 'integer',
        'unit_id' => 'integer',
        'tax_id' => 'integer',
        'quantity' => 'integer',
        'rate' => 'float',
        'tax_amount' => 'float',
        'discount_amount' => 'float',
        'tax_line_type' => TaxLineTypeEnum::class,
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockLayers(): HasMany
    {
        return $this->hasMany(StockLayer::class, 'source_bill_item_id');
    }
}

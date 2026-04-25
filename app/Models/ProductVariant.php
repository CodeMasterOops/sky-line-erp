<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_id',
        'sku',
        'sales_price',
        'purchase_price',
        'is_default',
    ];

    protected $casts = [
        'purchase_price' => 'double',
        'sales_price' => 'double',
        'is_default' => 'boolean',
    ];

    public function getVariantLabelAttribute(): ?string
    {
        if ($this->relationLoaded('variantOptions')) {
            $list = [];

            foreach ($this->variantOptions as $value) {
                $list[] = $value->value;
            }

            return implode('/', $list);
        }

        return null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variantOptions(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variant', 'product_variant_id', 'attribute_value_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function stockLayers(): HasMany
    {
        return $this->hasMany(StockLayer::class);
    }
}

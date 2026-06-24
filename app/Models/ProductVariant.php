<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Enums\ProductTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use BranchTenant;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'product_id',
        'sku',
        'barcode',
        'sales_price',
        'purchase_price',
        'is_default',
        'is_serialized',
        'is_batch_tracked',
    ];

    protected $casts = [
        'purchase_price' => 'double',
        'sales_price' => 'double',
        'is_default' => 'boolean',
        'is_serialized' => 'boolean',
        'is_batch_tracked' => 'boolean',
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

    public function getVariantNameAttribute(): ?string
    {
        $variantName = '';

        if ($this->relationLoaded('product')) {
            $variantName .= $this->product->name ?? '';

            if ($this->variant_label) {
                $variantName .= '-'.$this->variant_label;
            }
        }

        return $variantName;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isService(): bool
    {
        if ($this->relationLoaded('product') && $this->product !== null) {
            return $this->product->isService();
        }

        return Product::query()
            ->whereKey($this->product_id)
            ->where('product_type', ProductTypeEnum::SERVICE->value)
            ->exists();
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

<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\ProductTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_category_id',
        'product_type',
        'name',
        'code',
        'hsn_code',
        'image',
        'unit_id',
        'brand_id',
        'has_variants',
        'reorder_quantity',
        'min_stock_level',
        'description',
    ];

    protected $casts = [
        'product_type' => ProductTypeEnum::class,
        'sales_price' => 'float',
        'purchase_price' => 'float',
        'reorder_quantity' => 'integer',
        'min_stock_level' => 'float',
        'has_variants' => 'boolean',
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('name', 'like', $key);
                $q->orWhere('code', 'like', $key);
            });
        }

        if (! empty($param['product_category_id'])) {
            $query->where('product_category_id', $param['product_category_id']);
        }

        if (! empty($param['brand_id'])) {
            $query->where('brand_id', $param['brand_id']);
        }

        return $query;
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function defaultVariant(): HasOne
    {
        //        return $this->hasOne(ProductVariant::class)->where('is_default', true);
        return $this->hasOne(ProductVariant::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}

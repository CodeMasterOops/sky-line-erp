<?php

namespace App\Models;

use App\Enums\ItemRoleEnum;
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
        'import_batch_id',
        'product_category_id',
        'product_type',
        'item_role',
        'name',
        'code',
        'hsn_code',
        'image',
        'unit_id',
        'brand_id',
        'tax_id',
        'has_variants',
        'reorder_quantity',
        'min_stock_level',
        'description',
    ];

    protected $casts = [
        'product_type' => ProductTypeEnum::class,
        'item_role' => ItemRoleEnum::class,
        'sales_price' => 'float',
        'purchase_price' => 'float',
        'reorder_quantity' => 'integer',
        'min_stock_level' => 'float',
        'has_variants' => 'boolean',
    ];

    public function isService(): bool
    {
        return $this->product_type === ProductTypeEnum::SERVICE;
    }

    public function isPhysicalProduct(): bool
    {
        return $this->product_type === ProductTypeEnum::PRODUCT;
    }

    public function scopeService($query)
    {
        return $query->where('product_type', ProductTypeEnum::SERVICE->value);
    }

    public function scopePhysical($query)
    {
        return $query->where('product_type', ProductTypeEnum::PRODUCT->value);
    }

    public function scopeRawMaterial($query)
    {
        return $query->where('item_role', ItemRoleEnum::RawMaterial->value);
    }

    public function scopeFinishedGood($query)
    {
        return $query->where('item_role', ItemRoleEnum::FinishedGood->value);
    }

    /**
     * @return list<int>
     */
    public static function parseWarehouseIds(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $raw = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $raw
        ), static fn (int $id): bool => $id > 0)));
    }

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

        if (! empty($param['product_type'])) {
            $query->where('product_type', $param['product_type']);
        }

        if (! empty($param['item_role'])) {
            $query->where('item_role', $param['item_role']);
        }

        $warehouseIds = self::parseWarehouseIds($param['warehouse_ids'] ?? null);

        if ($warehouseIds !== []) {
            $query->physical()
                ->whereHas('variants.stocks', fn ($q) => $q->whereIn('warehouse_id', $warehouseIds));
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

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true)->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}

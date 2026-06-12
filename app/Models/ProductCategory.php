<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCategory extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'description',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeLeafOnly(Builder $query): Builder
    {
        return $query->whereDoesntHave('children');
    }

    public function isLeaf(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isEmpty();
        }

        if (array_key_exists('children_count', $this->attributes)) {
            return (int) $this->children_count === 0;
        }

        return ! $this->children()->exists();
    }

    /**
     * @param  array<int, self>|null  $byId
     */
    public function fullPath(?array $byId = null): string
    {
        if ($byId !== null) {
            return self::buildFullPathFromMap($this, $byId);
        }

        $segments = [$this->name];
        $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();

        while ($parent) {
            array_unshift($segments, $parent->name);
            $parent = $parent->relationLoaded('parent') ? $parent->parent : $parent->parent()->first();
        }

        return implode(' > ', $segments);
    }

    /**
     * @param  array<int, self>  $byId
     */
    public static function buildFullPathFromMap(self $category, array $byId): string
    {
        $segments = [$category->name];
        $parentId = $category->parent_id;

        while ($parentId !== null && isset($byId[$parentId])) {
            $parent = $byId[$parentId];
            array_unshift($segments, $parent->name);
            $parentId = $parent->parent_id;
        }

        return implode(' > ', $segments);
    }
}

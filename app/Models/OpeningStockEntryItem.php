<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningStockEntryItem extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'opening_stock_entry_id',
        'product_variant_id',
        'unit_id',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'float',
        ];
    }

    public function openingStockEntry(): BelongsTo
    {
        return $this->belongsTo(OpeningStockEntry::class);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryValuationSnapshot extends Model
{
    protected $fillable = [
        'company_id',
        'snapshot_date',
        'product_variant_id',
        'warehouse_id',
        'qty_remaining',
        'unit_cost',
        'total_value',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date:Y-m-d',
            'qty_remaining' => 'integer',
            'unit_cost' => 'float',
            'total_value' => 'float',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

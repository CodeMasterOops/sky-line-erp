<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLayer extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_variant_id',
        'warehouse_id',
        'bin_id',
        'qty_remaining',
        'unit_cost',
        'received_at',
        'lot_number',
        'source_bill_item_id',
    ];

    protected $casts = [
        'qty_remaining' => 'integer',
        'unit_cost' => 'float',
        'received_at' => 'datetime',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function sourceBillItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class, 'source_bill_item_id');
    }
}

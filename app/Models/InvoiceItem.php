<?php

namespace App\Models;

use App\Traits\HasDiscount;
use App\Enums\TaxLineTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasDiscount;
    use SoftDeletes;

    protected $fillable = [
        'invoice_id',
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
        'invoice_id' => 'integer',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
}

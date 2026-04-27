<?php

namespace App\Models;

use App\Traits\HasDiscount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    use HasDiscount;
    use SoftDeletes;

    protected $fillable = [
        'credit_note_id',
        'invoice_item_id',
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'unit_id',
        'rate',
        'tax_id',
        'tax_amount',
        'discount_amount',
    ];

    protected $casts = [
        'credit_note_id' => 'integer',
        'invoice_item_id' => 'integer',
        'product_variant_id' => 'integer',
        'warehouse_id' => 'integer',
        'unit_id' => 'integer',
        'tax_id' => 'integer',
        'quantity' => 'integer',
        'rate' => 'float',
        'tax_amount' => 'float',
        'discount_amount' => 'float',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
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

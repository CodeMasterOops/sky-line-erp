<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'product_variant_id',
        'warehouse_id',
        'reservable_type',
        'reservable_id',
        'quantity',
        'reserved_at',
        'released_at',
        'reserved_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function reservable(): MorphTo
    {
        return $this->morphTo();
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('released_at');
    }
}

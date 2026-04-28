<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    use MultiTenant, SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_variant_id',
        'warehouse_id',
        'batch_no',
        'lot_no',
        'mfg_date',
        'expiry_date',
        'initial_qty',
        'remaining_qty',
        'unit_cost',
        'status',
        'remarks',
    ];

    protected $casts = [
        'mfg_date' => 'date',
        'expiry_date' => 'date',
        'initial_qty' => 'float',
        'remaining_qty' => 'float',
        'unit_cost' => 'float',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** FEFO scope: order by expiry date ascending (nulls last) */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('remaining_qty', '>', 0)
            ->orderByRaw('expiry_date IS NULL ASC')
            ->orderBy('expiry_date');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString());
    }
}

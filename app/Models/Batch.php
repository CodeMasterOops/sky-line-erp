<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\MultiTenant;
use App\Enums\BatchStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    use Auditable, MultiTenant, SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'mfg_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'initial_qty' => 'float',
            'remaining_qty' => 'float',
            'unit_cost' => 'float',
            'status' => BatchStatusEnum::class,
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockLayers(): HasMany
    {
        return $this->hasMany(StockLayer::class);
    }

    /**
     * Whether stock may currently be issued out of this batch.
     */
    public function isIssuable(): bool
    {
        return $this->status instanceof BatchStatusEnum
            ? $this->status->isIssuable()
            : $this->status === BatchStatusEnum::Active->value;
    }

    public function hasExpired(): bool
    {
        return $this->expiry_date !== null
            && $this->expiry_date->lt(now()->startOfDay());
    }

    /**
     * Total remaining quantity held in non-issuable lots (quarantine, expired,
     * recalled) for a variant at a warehouse. This stock is still on-hand and
     * still owned, but must be excluded from "available to sell".
     */
    public static function heldQuantity(int $companyId, int $productVariantId, int $warehouseId): float
    {
        return (float) static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', BatchStatusEnum::heldValues())
            ->sum('remaining_qty');
    }

    /**
     * Recompute remaining_qty from the cost ledger (stock layers) — the single
     * source of truth — and flip auto-managed status between active/depleted.
     */
    public static function reconcileRemaining(int $batchId): void
    {
        $batch = static::withoutGlobalScopes()
            ->lockForUpdate()
            ->find($batchId);

        if (! $batch) {
            return;
        }

        $remaining = (float) StockLayer::withoutGlobalScopes()
            ->where('batch_id', $batchId)
            ->sum('qty_remaining');

        $batch->remaining_qty = $remaining;

        $status = $batch->status instanceof BatchStatusEnum
            ? $batch->status
            : BatchStatusEnum::tryFrom((string) $batch->status);

        if ($status?->isAutoManaged()) {
            $batch->status = $remaining > 0
                ? BatchStatusEnum::Active
                : BatchStatusEnum::Depleted;
        }

        $batch->save();
    }

    /**
     * Find (or create) the equivalent batch row in a different warehouse so that
     * stock transferred between warehouses keeps an accurate, warehouse-bound lot
     * record instead of pointing at the source warehouse's batch.
     */
    public static function findOrCreateForTransfer(self $source, int $warehouseId): self
    {
        if ((int) $source->warehouse_id === $warehouseId) {
            return $source;
        }

        $batch = static::withoutGlobalScopes()
            ->where('company_id', $source->company_id)
            ->where('product_variant_id', $source->product_variant_id)
            ->where('warehouse_id', $warehouseId)
            ->where('batch_no', $source->batch_no)
            ->first();

        if ($batch) {
            return $batch;
        }

        return static::create([
            'company_id' => $source->company_id,
            'product_variant_id' => $source->product_variant_id,
            'warehouse_id' => $warehouseId,
            'batch_no' => $source->batch_no,
            'lot_no' => $source->lot_no,
            'mfg_date' => $source->mfg_date,
            'expiry_date' => $source->expiry_date,
            'initial_qty' => 0,
            'remaining_qty' => 0,
            'unit_cost' => $source->unit_cost,
            'status' => BatchStatusEnum::Active,
        ]);
    }

    /** FEFO scope: order by expiry date ascending (nulls last) */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->where('status', BatchStatusEnum::Active->value)
            ->where('remaining_qty', '>', 0)
            ->where(function (Builder $q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now()->toDateString());
            })
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expiry_date');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', BatchStatusEnum::Active->value)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString());
    }
}

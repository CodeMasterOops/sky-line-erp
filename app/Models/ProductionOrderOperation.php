<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderOperation extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'production_order_id',
        'bom_operation_id',
        'sequence',
        'name',
        'work_center',
        'status',
        'started_at',
        'completed_at',
        'started_by',
        'completed_by',
        'remarks',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'actual_minutes',
        'standard_minutes',
    ];

    /** Actual elapsed minutes between start and completion, null while still running. */
    public function getActualMinutesAttribute(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) round($this->started_at->diffInSeconds($this->completed_at) / 60);
    }

    /** Standard (planned) minutes from the BOM operation, when that relation is loaded. */
    public function getStandardMinutesAttribute(): ?int
    {
        if (! $this->relationLoaded('bomOperation') || ! $this->bomOperation) {
            return null;
        }

        return $this->bomOperation->duration_minutes !== null
            ? (int) $this->bomOperation->duration_minutes
            : null;
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function bomOperation(): BelongsTo
    {
        return $this->belongsTo(BomOperation::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

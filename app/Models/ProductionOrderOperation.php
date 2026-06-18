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

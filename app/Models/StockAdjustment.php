<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockAdjustment extends Model
{
    use BranchTenant;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'reference_no',
        'date',
        'warehouse_id',
        'remarks',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'approved_at' => 'datetime',
        'status' => StatusEnum::class,
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}

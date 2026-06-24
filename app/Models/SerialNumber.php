<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialNumber extends Model
{
    use BranchTenant;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'product_variant_id',
        'serial_no',
        'batch_no',
        'batch_id',
        'expiry_date',
        'status',
        'warehouse_id',
        'receipt_movement_id',
        'issue_movement_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receiptMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'receipt_movement_id');
    }

    public function issueMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'issue_movement_id');
    }
}

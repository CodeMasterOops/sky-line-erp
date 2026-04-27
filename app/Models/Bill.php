<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\HasDiscount;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Bill extends Model
{
    use HasDiscount;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'party_id',
        'purchase_order_id',
        'bill_no',
        'bill_date',
        'due_date',
        'remarks',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'voided_at',
        'status',
    ];

    protected $casts = [
        'fiscal_year_id' => 'integer',
        'party_id' => 'integer',
        'purchase_order_id' => 'integer',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
        'status' => StatusEnum::class,
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where('bill_no', 'like', $key);
        }

        if (! empty($param['party_id'])) {
            $query->where('party_id', $param['party_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        return $query;
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function paymentAllocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'payable');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function createUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function approveUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approve_user_id');
    }

    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }
}

<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'party_id',
        'order_no',
        'order_date',
        'remarks',
        'order_discount_type',
        'order_discount_value',
        'order_discount_amount',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'fiscal_year_id' => 'integer',
        'party_id' => 'integer',
        'order_discount_value' => 'float',
        'order_discount_amount' => 'float',
        'approved_at' => 'datetime',
        'status' => StatusEnum::class,
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['party_id'])) {
            $query->where('party_id', $param['party_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($query) use ($key) {
                $query->where('order_no', 'like', $key);
            });
        }

        return $query;
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
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
}

<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChallan extends Model
{
    use BranchTenant;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'fiscal_year_id',
        'party_id',
        'reference_type',
        'reference_id',
        'challan_no',
        'challan_date',
        'warehouse_id',
        'delivery_address',
        'remarks',
        'receiver_name',
        'status',
        'create_user_id',
        'approve_user_id',
        'approved_at',
    ];

    protected $casts = [
        'challan_date' => 'date',
        'approved_at' => 'datetime',
        'status' => StatusEnum::class,
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where('challan_no', 'like', $key);
        }

        return $query;
    }

    public function challanItems(): HasMany
    {
        return $this->hasMany(DeliveryChallanItem::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
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

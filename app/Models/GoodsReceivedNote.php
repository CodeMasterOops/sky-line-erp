<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceivedNote extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'purchase_order_id',
        'party_id',
        'warehouse_id',
        'grn_no',
        'received_date',
        'supplier_invoice_no',
        'remarks',
        'status',
        'create_user_id',
        'approve_user_id',
        'approved_at',
    ];

    protected $casts = [
        'received_date' => 'date',
        'approved_at' => 'datetime',
        'status' => StatusEnum::class,
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where('grn_no', 'like', $key);
        }

        return $query;
    }

    public function grnItems(): HasMany
    {
        return $this->hasMany(GrnItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
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

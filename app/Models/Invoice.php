<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\HasDiscount;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use HasDiscount;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'party_id',
        'reference_type',
        'reference_id',
        'invoice_no',
        'bijak_no',
        'buyer_pan',
        'invoice_date',
        'invoice_date_bs',
        'due_date',
        'remarks',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'voided_at',
        'status',
        'ird_sync_status',
        'ird_internal_id',
        'ird_qr_data',
        'ird_synced_at',
        'ird_error',
    ];

    protected $casts = [
        'fiscal_year_id' => 'integer',
        'party_id' => 'integer',
        'reference_id' => 'integer',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
        'ird_synced_at' => 'datetime',
        'status' => StatusEnum::class,
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $query->where(function ($query) use ($param) {
                $key = '%'.trim($param['search']).'%';
                $query->where('invoice_no', 'like', $key);
            });
        }

        if (! empty($param['party_id'])) {
            $query->where('party_id', $param['party_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        return $query;
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
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
}

<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BranchTenant;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'fiscal_year_id',
        'party_id',
        'payment_no',
        'payment_date',
        'payment_mode_id',
        'account_id',
        'reference_no',
        'remarks',
        'tds_category',
        'tds_rate',
        'tds_amount',
        'gross_amount',
        'currency_code',
        'exchange_rate',
        'create_user_id',
        'approve_user_id',
        'approved_at',
        'status',
    ];

    protected $casts = [
        'fiscal_year_id' => 'integer',
        'party_id' => 'integer',
        'payment_mode_id' => 'integer',
        'account_id' => 'integer',
        'approved_at' => 'datetime',
        'status' => StatusEnum::class,
        'tds_rate' => 'float',
        'tds_amount' => 'float',
        'gross_amount' => 'float',
        'exchange_rate' => 'float',
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where('payment_no', 'like', $key);
        }

        if (! empty($param['party_id'])) {
            $query->where('party_id', $param['party_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        return $query;
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
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

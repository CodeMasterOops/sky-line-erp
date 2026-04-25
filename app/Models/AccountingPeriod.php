<?php

namespace App\Models;

use App\Enums\AccountingPeriodStatusEnum;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'period_number',
        'period_name',
        'start_date',
        'end_date',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'status' => AccountingPeriodStatusEnum::class,
        'period_number' => 'integer',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === AccountingPeriodStatusEnum::OPEN;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            AccountingPeriodStatusEnum::CLOSED,
            AccountingPeriodStatusEnum::LOCKED,
        ]);
    }
}

<?php

namespace App\Models;

use App\Models\Journal;
use App\Traits\MultiTenant;
use App\Enums\PayrollStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRun extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'month',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'processed_by',
        'processed_at',
        'journal_id',
        'paid_account_id',
        'paid_at',
    ];

    protected $casts = [
        'fiscal_year_id' => 'integer',
        'month' => 'integer',
        'status' => PayrollStatusEnum::class,
        'total_gross' => 'float',
        'total_deductions' => 'float',
        'total_net' => 'float',
        'processed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function paidAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_account_id');
    }

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $param['fiscal_year_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        return $query;
    }
}

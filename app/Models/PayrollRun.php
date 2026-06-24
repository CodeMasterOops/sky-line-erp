<?php

namespace App\Models;

use Carbon\Carbon;
use App\Traits\MultiTenant;
use App\Enums\PayrollStatusEnum;
use Illuminate\Database\Eloquent\Model;
use App\Services\Nepal\NepaliDateService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRun extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'bs_year',
        'bs_month',
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
        'bs_year' => 'integer',
        'bs_month' => 'integer',
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

    /**
     * Whether this run is keyed to a Bikram Sambat (BS) month.
     */
    public function isBsPeriod(): bool
    {
        return ! empty($this->bs_year) && ! empty($this->bs_month);
    }

    /**
     * The AD date range [start, end] covered by this payroll period.
     *
     * A BS month spans two AD months, so the range is resolved from the BS
     * calendar when bs_year/bs_month are set; otherwise from the legacy AD month.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public function periodRange(): array
    {
        if ($this->isBsPeriod()) {
            $svc = app(NepaliDateService::class);
            $start = $svc->bsToAd($this->bs_year, $this->bs_month, 1)->startOfDay();
            $end = $svc->bsToAd($this->bs_year, $this->bs_month, $svc->daysInBsMonth($this->bs_year, $this->bs_month))->endOfDay();

            return [$start, $end];
        }

        $fiscalYear = $this->fiscalYear ?? FiscalYear::findOrFail($this->fiscal_year_id);
        $startYear = $fiscalYear->start_date->year;
        $year = $this->month >= $fiscalYear->start_date->month ? $startYear : $startYear + 1;
        $start = Carbon::create($year, $this->month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()->endOfDay()];
    }

    /**
     * Human-readable period label, e.g. "Shrawan 2081" (BS) or "August 2024" (AD).
     */
    public function periodLabel(): string
    {
        if ($this->isBsPeriod()) {
            return app(NepaliDateService::class)->monthName($this->bs_month).' '.$this->bs_year;
        }

        [$start] = $this->periodRange();

        return $start->format('F Y');
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

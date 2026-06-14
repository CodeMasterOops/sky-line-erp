<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\TdsCategoryEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdsReceivable extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'party_id',
        'tds_deduction_id',
        'certificate_no',
        'certificate_date',
        'tds_category',
        'tds_rate',
        'base_amount',
        'tds_amount',
        'period_month',
        'status',
        'remarks',
        'settled_at',
        'settlement_journal_id',
        'create_user_id',
        'approve_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'tds_category' => TdsCategoryEnum::class,
            'tds_rate' => 'float',
            'base_amount' => 'float',
            'tds_amount' => 'float',
            'certificate_date' => 'date',
            'approved_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function tdsDeduction(): BelongsTo
    {
        return $this->belongsTo(TdsDeduction::class);
    }

    public function settlementJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'settlement_journal_id');
    }

    public function createUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'create_user_id');
    }

    public function approveUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approve_user_id');
    }

    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }
}

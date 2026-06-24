<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Enums\TdsCategoryEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdsDeduction extends Model
{
    use Auditable;
    use BranchTenant;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'fiscal_year_id',
        'deductible_type',
        'deductible_id',
        'party_id',
        'tds_category',
        'base_amount',
        'tds_rate',
        'tds_amount',
        'period_month',
        'journal_id',
        'deducted_at',
    ];

    protected $casts = [
        'base_amount' => 'float',
        'tds_rate' => 'float',
        'tds_amount' => 'float',
        'tds_category' => TdsCategoryEnum::class,
        'deducted_at' => 'datetime',
    ];

    public function deductible(): MorphTo
    {
        return $this->morphTo();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}

<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use App\Enums\LandedCostTreatmentEnum;
use Illuminate\Database\Eloquent\Model;
use App\Enums\LandedCostAllocationMethodEnum;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCost extends Model
{
    use BranchTenant;
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'bill_id',
        'goods_received_note_id',
        'cost_type',
        'description',
        'treatment',
        'allocation_method',
        'amount',
        'vat_amount',
        'vat_claimable_amount',
        'account_id',
        'journal_id',
    ];

    protected $casts = [
        'treatment' => LandedCostTreatmentEnum::class,
        'allocation_method' => LandedCostAllocationMethodEnum::class,
        'amount' => 'float',
        'vat_amount' => 'float',
        'vat_claimable_amount' => 'float',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function goodsReceivedNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNote::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }
}

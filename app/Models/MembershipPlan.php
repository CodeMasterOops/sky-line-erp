<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\MultiTenant;
use Carbon\CarbonInterface;
use App\Traits\BranchTenant;
use App\Enums\DurationUnitEnum;
use Illuminate\Database\Eloquent\Model;
use App\Enums\MembershipDurationPresetEnum;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A sellable membership term — Monthly, Quarterly, Half-Yearly, Yearly or any
 * custom length.
 *
 * NOT to be confused with App\Models\Plan, which is the SaaS package a *company*
 * subscribes to.
 */
class MembershipPlan extends Model
{
    /** @use HasFactory<\Database\Factories\MembershipPlanFactory> */
    use Auditable;
    use BranchTenant;
    use HasFactory;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'product_id',
        'code',
        'name',
        'description',
        'duration_unit',
        'duration_value',
        'preset',
        'price',
        'joining_fee',
        'grace_days',
        'max_freeze_days',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_unit' => DurationUnitEnum::class,
            'preset' => MembershipDurationPresetEnum::class,
            'duration_value' => 'integer',
            'price' => 'float',
            'joining_fee' => 'float',
            'grace_days' => 'integer',
            'max_freeze_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The service item this plan bills through.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The last day of a term starting on the given date.
     *
     * Inclusive of the start day: a one-month term from 1 Jan ends 31 Jan, not
     * 1 Feb. Month arithmetic never overflows, so 31 Jan + 1 month is 28/29 Feb.
     */
    public function endDateFrom(CarbonInterface $startDate): CarbonInterface
    {
        return $this->duration_unit
            ->addTo($startDate, $this->duration_value)
            ->subDay();
    }

    /**
     * Human label for the term, e.g. "3 Months" or "Yearly".
     */
    public function getDurationLabelAttribute(): string
    {
        $preset = $this->preset ?? MembershipDurationPresetEnum::forDuration($this->duration_unit, $this->duration_value);

        if ($preset !== MembershipDurationPresetEnum::Custom) {
            return $preset->label();
        }

        $unit = $this->duration_unit->label();

        return $this->duration_value.' '.($this->duration_value === 1 ? $unit : $unit.'s');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('name', 'like', $key)->orWhere('code', 'like', $key);
            });
        }

        if (isset($param['is_active']) && $param['is_active'] !== '') {
            $query->where('is_active', filter_var($param['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($param['preset'])) {
            $query->where('preset', $param['preset']);
        }

        return $query;
    }
}

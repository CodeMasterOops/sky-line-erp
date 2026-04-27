<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\FixedAssetDepreciationMethodEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAsset extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'fixed_asset_category_id',
        'asset_code',
        'name',
        'purchase_date',
        'purchase_cost',
        'salvage_value',
        'useful_life_years',
        'depreciation_method',
        'accumulated_depreciation',
        'last_depreciation_date',
        'disposal_date',
        'disposal_proceeds',
        'status',
        'asset_account_id',
        'depreciation_account_id',
        'accumulated_depreciation_account_id',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'last_depreciation_date' => 'date',
        'disposal_date' => 'date',
        'purchase_cost' => 'float',
        'salvage_value' => 'float',
        'useful_life_years' => 'float',
        'accumulated_depreciation' => 'float',
        'disposal_proceeds' => 'float',
        'depreciation_method' => FixedAssetDepreciationMethodEnum::class,
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('name', 'like', $key)->orWhere('asset_code', 'like', $key);
            });
        }

        return $query;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'fixed_asset_category_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function depreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function netBookValue(): float
    {
        return round($this->purchase_cost - $this->accumulated_depreciation, 2);
    }

    public function annualDepreciation(): float
    {
        if ($this->depreciation_method === FixedAssetDepreciationMethodEnum::SLM) {
            return round(($this->purchase_cost - $this->salvage_value) / max($this->useful_life_years, 1), 2);
        }

        $depreciableBase = $this->purchase_cost - $this->accumulated_depreciation - $this->salvage_value;

        return round(max($depreciableBase, 0) / max($this->useful_life_years, 1), 2);
    }
}

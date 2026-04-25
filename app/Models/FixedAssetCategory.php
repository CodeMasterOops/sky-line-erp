<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetCategory extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'depreciation_method',
        'useful_life_years',
        'salvage_value_percent',
        'asset_account_id',
        'depreciation_account_id',
        'accumulated_depreciation_account_id',
    ];

    protected $casts = [
        'useful_life_years' => 'float',
        'salvage_value_percent' => 'float',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
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
}

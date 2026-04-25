<?php

namespace App\Models;

use App\Enums\TaxTypeEnum;
use App\Enums\TdsCategoryEnum;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'rate',
        'type',
        'tds_category',
        'is_system',
    ];

    protected $casts = [
        'rate' => 'float',
        'type' => TaxTypeEnum::class,
        'tds_category' => TdsCategoryEnum::class,
        'is_system' => 'boolean',
    ];

    public function scopeVat($query)
    {
        return $query->whereIn('type', [
            TaxTypeEnum::VAT_STANDARD->value,
            TaxTypeEnum::VAT_EXEMPT->value,
            TaxTypeEnum::VAT_ZERO_RATED->value,
        ]);
    }

    public function scopeTds($query)
    {
        return $query->where('type', TaxTypeEnum::TDS->value);
    }
}

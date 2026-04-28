<?php

namespace App\Models;

use App\Enums\TaxTypeEnum;
use App\Traits\MultiTenant;
use App\Enums\TdsCategoryEnum;
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

    protected static function booted(): void
    {
        static::saving(function (self $tax) {
            if ($tax->type === TaxTypeEnum::TDS && $tax->tds_category instanceof TdsCategoryEnum) {
                $tax->rate = $tax->tds_category->rate();
            }
        });
    }

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

    /**
     * Line-item taxes are VAT types only.
     * TDS is a withholding tax deducted at payment time, not on invoice lines.
     */
    public function scopeLineItem($query)
    {
        return $query->vat();
    }

    public function isLineItemTax(): bool
    {
        return $this->type !== null && $this->type->isVat();
    }
}

<?php

namespace App\Models;

use App\Enums\TaxTypeEnum;
use App\Traits\MultiTenant;
use App\Enums\TdsCategoryEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'calculation_type',
        'is_inclusive',
        'is_compound',
        'sequence',
        'gl_account_id',
    ];

    protected $casts = [
        'rate' => 'float',
        'type' => TaxTypeEnum::class,
        'tds_category' => TdsCategoryEnum::class,
        'is_system' => 'boolean',
        'is_inclusive' => 'boolean',
        'is_compound' => 'boolean',
        'sequence' => 'integer',
        'gl_account_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $tax) {
            if ($tax->type === TaxTypeEnum::TDS && $tax->tds_category instanceof TdsCategoryEnum) {
                $tax->rate = $tax->tds_category->rate();
            }

            // Nepal VAT Act: VAT standard rate is fixed at 13%. Enforce it here
            // so a misconfiguration can never cause incorrect tax calculations.
            if ($tax->type === TaxTypeEnum::VAT_STANDARD) {
                $tax->rate = 13.0;
            }
        });
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function productTaxes(): HasMany
    {
        return $this->hasMany(ProductTax::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_taxes');
    }

    public function taxGroups(): BelongsToMany
    {
        return $this->belongsToMany(TaxGroup::class, 'tax_group_members')
            ->withPivot('sequence')
            ->orderByPivot('sequence');
    }

    public function partyExemptions(): HasMany
    {
        return $this->hasMany(PartyTaxExemption::class);
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

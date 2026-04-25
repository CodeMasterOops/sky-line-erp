<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\SalaryComponentTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryComponent extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'calculation_type',
        'is_taxable',
        'is_active',
        'account_id',
    ];

    protected $casts = [
        'type' => SalaryComponentTypeEnum::class,
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function salaryStructureItems(): HasMany
    {
        return $this->hasMany(SalaryStructureItem::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

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
        'is_basic',
        'calculation_type',
        'percentage_base',
        'is_taxable',
        'is_active',
        'is_system',
        'system_code',
        'account_id',
        'contra_account_id',
    ];

    protected $casts = [
        'type' => SalaryComponentTypeEnum::class,
        'is_basic' => 'boolean',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function salaryStructureItems(): HasMany
    {
        return $this->hasMany(SalaryStructureItem::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }
}

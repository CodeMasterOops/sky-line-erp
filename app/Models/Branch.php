<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use MultiTenant, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        'pan',
        'is_head_office',
        'is_active',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}

<?php

namespace App\Models;

use App\Enums\UserTypeEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Enums\InventoryCostingMethodEnum;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fiscal_year_id',
        'company_name',
        'code',
        'legal_name',
        'pan',
        'logo',
        'phone',
        'landline',
        'email',
        'website',
        'address',
        'is_active',
        'inventory_costing_method',
        'ird_username',
        'ird_password',
        'ird_branch_office',
        'ird_unit_name',
        'ird_fiscal_device',
        'ird_ebs_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ird_ebs_enabled' => 'boolean',
            'inventory_costing_method' => InventoryCostingMethodEnum::class,
        ];
    }

    public function setIrdPasswordAttribute(?string $value): void
    {
        $this->attributes['ird_password'] = $value ? encrypt($value) : null;
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? Storage::url($this->logo)
            : '';
    }

    public function setLogoAttribute($value): void
    {
        if (! empty($value) && $value instanceof UploadedFile) {
            $this->attributes['logo'] = $value->store('company/logo');
        }
    }

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('company_name', 'like', $key)
                    ->orWhere('email', 'like', $key)
                    ->orWhere('phone', 'like', $key);
            });
        }

        return $query;
    }

    public function admin(): HasOne
    {
        return $this->hasOne(User::class)->where('user_type', UserTypeEnum::ADMIN->value);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}

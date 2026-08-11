<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * The industry a company is in (Retail, Gym / Fitness, Service Business, …).
 * A category carries a default module set that is applied at provisioning and
 * can be re-applied later; it never overrides an explicit per-company decision.
 */
class CompanyCategory extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyCategoryFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CompanyCategoryModule::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * The module keys this category switches on for a new company.
     *
     * @return list<string>
     */
    public function defaultModuleKeys(): array
    {
        return $this->modules
            ->where('is_default_enabled', true)
            ->pluck('module_key')
            ->values()
            ->all();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('name', 'like', $key)
                    ->orWhere('slug', 'like', $key);
            });
        }

        if (isset($param['is_active'])) {
            $query->where('is_active', filter_var($param['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }
}

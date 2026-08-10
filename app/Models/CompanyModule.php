<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\ModuleSourceEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A company's explicit decision about one module. An existing row always beats
 * the category default during resolution, which is what makes a Super Admin
 * override stick.
 *
 * Disabling writes `is_enabled = false` and nothing else: no rows are deleted,
 * archived or soft-deleted anywhere in the app, so re-enabling restores the
 * module exactly as it was.
 */
class CompanyModule extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyModuleFactory> */
    use HasFactory;
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'module_key',
        'is_enabled',
        'source',
        'settings',
        'enabled_at',
        'disabled_at',
        'updated_by_type',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'source' => ModuleSourceEnum::class,
            'settings' => 'array',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function updatedBy(): MorphTo
    {
        return $this->morphTo('updated_by');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForModule($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey);
    }
}

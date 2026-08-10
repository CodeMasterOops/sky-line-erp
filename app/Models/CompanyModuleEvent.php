<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\ModuleEventActionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit of every module change: who switched what, when, and why.
 * Surfaced in the Super Admin company screen so a tenant's module history is
 * explainable long after the fact.
 */
class CompanyModuleEvent extends Model
{
    use MultiTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'module_key',
        'action',
        'reason',
        'actor_type',
        'actor_id',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'action' => ModuleEventActionEnum::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }

    public function scopeFilter($query, array $param = [])
    {
        if (! empty($param['module_key'])) {
            $query->where('module_key', $param['module_key']);
        }

        if (! empty($param['action'])) {
            $query->where('action', $param['action']);
        }

        return $query;
    }
}

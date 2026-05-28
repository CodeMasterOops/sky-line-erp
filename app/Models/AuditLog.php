<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only audit row. Written by the Auditable trait — never updated.
 * The model intentionally has no `update()` / `save()` after creation hooks;
 * normal application code should only ever `create()` rows here.
 */
class AuditLog extends Model
{
    use MultiTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'auditable_type',
        'auditable_id',
        'event',
        'user_id',
        'user_type',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}

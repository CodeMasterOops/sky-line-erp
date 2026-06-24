<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of account security events (login, logout, password
 * change, deactivation, device revocation). Written by SecurityActivityLogger;
 * normal application code should only ever `create()` rows here.
 */
class SecurityActivity extends Model
{
    use MultiTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'user_id',
        'event',
        'description',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

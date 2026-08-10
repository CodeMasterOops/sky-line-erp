<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paused stretch of a membership term.
 *
 * Scoped through its parent membership rather than carrying its own tenant
 * columns (see config/tenancy.php, branch_owned_via_parent).
 */
class MembershipFreeze extends Model
{
    protected $fillable = [
        'membership_id',
        'from_date',
        'to_date',
        'days',
        'reason',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'days' => 'integer',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isRunning(): bool
    {
        return $this->to_date === null;
    }

    public function scopeRunning($query)
    {
        return $query->whereNull('to_date');
    }
}

<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationAuditLog extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'bank_reconciliation_id',
        'auditable_type',
        'auditable_id',
        'action',
        'before',
        'after',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

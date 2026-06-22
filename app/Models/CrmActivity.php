<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CrmActivity extends Model
{
    use BranchTenant;
    /** @use HasFactory<\Database\Factories\CrmActivityFactory> */
    use HasFactory;
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'subject_type',
        'subject_id',
        'causer_id',
        'type',
        'description',
        'properties',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}

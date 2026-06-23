<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\CrmLeadStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CrmLeadProfile extends Model
{
    /** @use HasFactory<\Database\Factories\CrmLeadProfileFactory> */
    use HasFactory;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'party_id',
        'status',
        'source',
        'assigned_to_user_id',
        'expected_value',
        'expected_close_date',
        'next_follow_up_at',
        'qualified_at',
        'converted_at',
        'lost_reason',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CrmLeadStatusEnum::class,
            'expected_value' => 'float',
            'expected_close_date' => 'date:Y-m-d',
            'next_follow_up_at' => 'datetime',
            'qualified_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

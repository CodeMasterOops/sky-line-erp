<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\LeaveStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'days',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'days' => 'float',
        'status' => LeaveStatusEnum::class,
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['employee_id'])) {
            $query->where('employee_id', $param['employee_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        if (! empty($param['leave_type_id'])) {
            $query->where('leave_type_id', $param['leave_type_id']);
        }

        return $query;
    }
}

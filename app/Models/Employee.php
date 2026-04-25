<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\EmployeeStatusEnum;
use App\Enums\EmploymentTypeEnum;
use App\Enums\TdsCategoryEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'department_id',
        'designation_id',
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'gender',
        'dob',
        'join_date',
        'employment_type',
        'status',
        'bank_name',
        'bank_account_no',
        'pan',
        'tds_category',
        'photo',
        'address',
    ];

    protected $casts = [
        'dob' => 'date',
        'join_date' => 'date',
        'employment_type' => EmploymentTypeEnum::class,
        'status' => EmployeeStatusEnum::class,
        'tds_category' => TdsCategoryEnum::class,
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function activeSalaryStructure(): HasMany
    {
        return $this->hasMany(SalaryStructure::class)->where('is_active', true)->latest('effective_from');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['search'])) {
            $key = '%'.trim($param['search']).'%';
            $query->where(function ($q) use ($key) {
                $q->where('first_name', 'like', $key)
                    ->orWhere('last_name', 'like', $key)
                    ->orWhere('employee_code', 'like', $key)
                    ->orWhere('email', 'like', $key)
                    ->orWhere('phone', 'like', $key);
            });
        }

        if (! empty($param['department_id'])) {
            $query->where('department_id', $param['department_id']);
        }

        if (! empty($param['designation_id'])) {
            $query->where('designation_id', $param['designation_id']);
        }

        if (! empty($param['status'])) {
            $query->where('status', $param['status']);
        }

        if (! empty($param['employment_type'])) {
            $query->where('employment_type', $param['employment_type']);
        }

        return $query;
    }
}

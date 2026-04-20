<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'working_days',
        'present_days',
        'leave_days',
        'gross_salary',
        'total_deductions',
        'net_salary',
    ];

    protected $casts = [
        'working_days' => 'integer',
        'present_days' => 'integer',
        'leave_days' => 'integer',
        'gross_salary' => 'float',
        'total_deductions' => 'float',
        'net_salary' => 'float',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class);
    }
}

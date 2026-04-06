<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\AttendanceStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => AttendanceStatusEnum::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['employee_id'])) {
            $query->where('employee_id', $param['employee_id']);
        }

        if (! empty($param['month']) && ! empty($param['year'])) {
            $query->whereMonth('date', $param['month'])
                  ->whereYear('date', $param['year']);
        }

        if (! empty($param['date'])) {
            $query->whereDate('date', $param['date']);
        }

        return $query;
    }
}

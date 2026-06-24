<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Models\WorkSchedule;
use App\Services\TenantService;
use App\Enums\AttendanceStatusEnum;
use Illuminate\Database\Eloquent\Model;
use App\Services\Payroll\AttendanceCalculator;
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
        'worked_hours',
        'late_minutes',
        'early_leaving_minutes',
        'overtime_hours',
        'status',
        'note',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => AttendanceStatusEnum::class,
        'worked_hours' => 'float',
        'late_minutes' => 'integer',
        'early_leaving_minutes' => 'integer',
        'overtime_hours' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $attendance): void {
            $attendance->applyWorkTracking();
        });
    }

    /**
     * Derive worked hours / late / overtime from the company's default work schedule.
     */
    protected function applyWorkTracking(): void
    {
        $trackable = in_array($this->status, [AttendanceStatusEnum::PRESENT, AttendanceStatusEnum::LATE], true);

        if (! $trackable || ! $this->check_in || ! $this->check_out) {
            $this->worked_hours = 0;
            $this->late_minutes = 0;
            $this->early_leaving_minutes = 0;
            $this->overtime_hours = 0;

            return;
        }

        $companyId = $this->company_id ?? TenantService::companyId();
        $schedule = $companyId
            ? WorkSchedule::withoutGlobalScopes()->where('company_id', $companyId)->where('is_default', true)->first()
            : null;

        if (! $schedule) {
            return;
        }

        $result = app(AttendanceCalculator::class)->compute($this->check_in, $this->check_out, $schedule);

        $this->worked_hours = $result['worked_hours'];
        $this->late_minutes = $result['late_minutes'];
        $this->early_leaving_minutes = $result['early_leaving_minutes'];
        $this->overtime_hours = $result['overtime_hours'];

        if ($result['late_minutes'] > 0 && $this->status === AttendanceStatusEnum::PRESENT) {
            $this->status = AttendanceStatusEnum::LATE;
        }
    }

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

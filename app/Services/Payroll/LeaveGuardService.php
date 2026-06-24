<?php

namespace App\Services\Payroll;

use App\Models\FiscalYear;
use App\Enums\LeaveStatusEnum;
use App\Models\LeaveApplication;
use Illuminate\Validation\ValidationException;

/**
 * Validates leave applications against overlapping requests and the leave-type
 * annual balance.
 */
class LeaveGuardService
{
    /**
     * Reject a date range that overlaps an existing pending/approved leave for the employee.
     */
    public function assertNoOverlap(int $employeeId, string $from, string $to, ?int $exceptId = null): void
    {
        $overlaps = LeaveApplication::where('employee_id', $employeeId)
            ->whereIn('status', [LeaveStatusEnum::PENDING, LeaveStatusEnum::APPROVED])
            ->where('from_date', '<=', $to)
            ->where('to_date', '>=', $from)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'from_date' => 'This employee already has a leave request overlapping these dates.',
            ]);
        }
    }

    /**
     * Reject approval that would exceed the leave type's annual allowance for the
     * fiscal year containing the application's start date.
     */
    public function assertWithinBalance(LeaveApplication $application): void
    {
        $leaveType = $application->leaveType()->first();
        $allowed = (int) ($leaveType->days_allowed ?? 0);

        if ($allowed <= 0) {
            return; // no configured cap
        }

        $fiscalYear = FiscalYear::where('start_date', '<=', $application->from_date->toDateString())
            ->where('end_date', '>=', $application->from_date->toDateString())
            ->first();

        $query = LeaveApplication::where('employee_id', $application->employee_id)
            ->where('leave_type_id', $application->leave_type_id)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->where('id', '!=', $application->id);

        if ($fiscalYear) {
            $query->whereBetween('from_date', [
                $fiscalYear->start_date->toDateString(),
                $fiscalYear->end_date->toDateString(),
            ]);
        }

        $used = (float) $query->sum('days');

        if ($used + (float) $application->days > $allowed) {
            $remaining = max(0, $allowed - $used);
            throw ValidationException::withMessages([
                'days' => "Approving this exceeds the {$leaveType->name} allowance ({$allowed} days). Remaining: {$remaining}.",
            ]);
        }
    }
}

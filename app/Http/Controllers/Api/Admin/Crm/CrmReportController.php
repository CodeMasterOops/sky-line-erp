<?php

namespace App\Http\Controllers\Api\Admin\Crm;

use App\Models\Task;
use App\Models\FollowUp;
use Illuminate\Http\Request;
use App\Enums\TaskStatusEnum;
use App\Models\CrmLeadProfile;
use App\Annotation\Permissions;
use App\Enums\CrmLeadStatusEnum;
use App\Enums\FollowUpStatusEnum;
use App\Http\Controllers\Controller;

class CrmReportController extends Controller
{
    /**
     * Lead pipeline summary — lead count grouped by status. Scoped to the active
     * branch via the owning Party (whereHas applies Party's branch global scope).
     */
    #[Permissions('view_crm_report', group: 'crm_report', desc: 'View CRM Reports')]
    public function pipeline()
    {
        $byStatus = collect(CrmLeadStatusEnum::cases())->map(fn (CrmLeadStatusEnum $status): array => [
            'status' => $status->value,
            'label' => $status->label(),
            'count' => CrmLeadProfile::query()->whereHas('party')->where('status', $status->value)->count(),
        ]);

        return response()->json([
            'data' => [
                'by_status' => $byStatus,
                'total' => $byStatus->sum('count'),
            ],
        ]);
    }

    /**
     * Conversion rate over a date range (by lead creation date) plus the
     * average number of days from creation to conversion.
     */
    #[Permissions('view_crm_report', group: 'crm_report', desc: 'View CRM Reports')]
    public function conversion(Request $request)
    {
        $profiles = CrmLeadProfile::query()
            ->whereHas('party')
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('date_to')))
            ->get();

        $total = $profiles->count();

        $converted = $profiles
            ->where('status', CrmLeadStatusEnum::Converted)
            ->filter(fn (CrmLeadProfile $profile): bool => $profile->converted_at !== null);

        $convertedCount = $converted->count();

        $avgDays = $converted->isEmpty()
            ? null
            : round($converted->avg(fn (CrmLeadProfile $profile): float => $profile->created_at->diffInDays($profile->converted_at)), 1);

        return response()->json([
            'data' => [
                'total_leads' => $total,
                'converted' => $convertedCount,
                'conversion_rate' => $total > 0 ? round($convertedCount / $total * 100, 1) : 0.0,
                'avg_days_to_convert' => $avgDays,
            ],
        ]);
    }

    /**
     * Follow-ups by status with due / overdue counts (branch-scoped, optional
     * per-user filter).
     */
    #[Permissions('view_crm_report', group: 'crm_report', desc: 'View CRM Reports')]
    public function followUps(Request $request)
    {
        $base = fn () => FollowUp::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')));

        $byStatus = collect(FollowUpStatusEnum::cases())->map(fn (FollowUpStatusEnum $status): array => [
            'status' => $status->value,
            'label' => $status->label(),
            'count' => $base()->where('status', $status->value)->count(),
        ]);

        return response()->json([
            'data' => [
                'by_status' => $byStatus,
                'due' => $base()
                    ->where('status', FollowUpStatusEnum::Pending->value)
                    ->where('scheduled_at', '<=', now())
                    ->count(),
                'overdue' => $base()
                    ->where('status', FollowUpStatusEnum::Pending->value)
                    ->whereDate('scheduled_at', '<', now()->toDateString())
                    ->count(),
            ],
        ]);
    }

    /**
     * Tasks by status with overdue count and open-task breakdown by assignee
     * (branch-scoped, optional per-user filter).
     */
    #[Permissions('view_crm_report', group: 'crm_report', desc: 'View CRM Reports')]
    public function tasks(Request $request)
    {
        $base = fn () => Task::query()
            ->when($request->filled('assigned_to_user_id'), fn ($q) => $q->where('assigned_to_user_id', $request->integer('assigned_to_user_id')));

        $closed = [TaskStatusEnum::Done->value, TaskStatusEnum::Cancelled->value];

        $byStatus = collect(TaskStatusEnum::cases())->map(fn (TaskStatusEnum $status): array => [
            'status' => $status->value,
            'label' => $status->label(),
            'count' => $base()->where('status', $status->value)->count(),
        ]);

        $byAssignee = $base()
            ->whereNotIn('status', $closed)
            ->whereNotNull('assigned_to_user_id')
            ->with('assignee')
            ->get()
            ->groupBy('assigned_to_user_id')
            ->map(fn ($group): array => [
                'user_id' => $group->first()->assigned_to_user_id,
                'name' => $group->first()->assignee?->name,
                'count' => $group->count(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'by_status' => $byStatus,
                'overdue' => $base()
                    ->whereNotIn('status', $closed)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->count(),
                'by_assignee' => $byAssignee,
            ],
        ]);
    }
}

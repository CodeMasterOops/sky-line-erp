<?php

namespace App\Services\Gym;

use App\Models\Membership;
use App\Models\MemberCheckIn;
use App\Enums\MembershipStatusEnum;

/**
 * The four questions a gym owner actually asks: who is on the books, who is
 * renewing, what the plans earn, and how busy the floor is.
 *
 * Every query runs through the tenant-scoped models, so branch isolation and
 * company isolation come from the same global scopes as the rest of the ERP.
 */
class GymReportService
{
    /**
     * Membership counts by status and by plan.
     *
     * @return array<string, mixed>
     */
    public function membershipSummary(?string $from = null, ?string $to = null): array
    {
        $byStatus = [];

        foreach (MembershipStatusEnum::cases() as $status) {
            $byStatus[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $this->windowed(Membership::query(), $from, $to)
                    ->where('status', $status->value)
                    ->count(),
            ];
        }

        $byPlan = $this->windowed(Membership::query(), $from, $to)
            ->with('membershipPlan')
            ->get()
            ->groupBy('membership_plan_id')
            ->map(fn ($terms) => [
                'plan' => $terms->first()->membershipPlan?->name ?? 'Unknown',
                'count' => $terms->count(),
                'active' => $terms->where('status', MembershipStatusEnum::Active)->count(),
            ])
            ->values()
            ->all();

        return [
            'by_status' => $byStatus,
            'by_plan' => $byPlan,
            'total' => $this->windowed(Membership::query(), $from, $to)->count(),
        ];
    }

    /**
     * How much of the period's business was renewals rather than new sales —
     * the closest thing to a retention number without a cohort analysis.
     *
     * @return array<string, mixed>
     */
    public function renewals(?string $from = null, ?string $to = null): array
    {
        $total = $this->windowed(Membership::query(), $from, $to)->count();
        $renewals = $this->windowed(Membership::query(), $from, $to)
            ->whereNotNull('renewed_from_id')
            ->count();

        $expiredInWindow = $this->windowed(Membership::query(), $from, $to, 'end_date')
            ->whereIn('status', [MembershipStatusEnum::Expired->value, MembershipStatusEnum::Cancelled->value])
            ->count();

        return [
            'terms_started' => $total,
            'renewals' => $renewals,
            'new_memberships' => $total - $renewals,
            'renewal_share' => $total > 0 ? round($renewals / $total * 100, 1) : 0.0,
            'lapsed_in_period' => $expiredInWindow,
            'rows' => $this->windowed(Membership::query(), $from, $to)
                ->whereNotNull('renewed_from_id')
                ->with(['member.party', 'membershipPlan'])
                ->orderByDesc('start_date')
                ->limit(100)
                ->get()
                ->map(fn (Membership $term): array => [
                    'membership_no' => $term->membership_no,
                    'member' => $term->member?->party?->name,
                    'plan' => $term->membershipPlan?->name,
                    'start_date' => $term->start_date?->toDateString(),
                    'end_date' => $term->end_date?->toDateString(),
                    'payable_amount' => $term->payable_amount,
                ])
                ->all(),
        ];
    }

    /**
     * What each plan brought in over the period.
     *
     * Read from the memberships rather than the invoices on purpose: it answers
     * "what did we sell", which stays true whether or not a term was billed.
     *
     * @return array<string, mixed>
     */
    public function revenueByPlan(?string $from = null, ?string $to = null): array
    {
        $rows = $this->windowed(Membership::query(), $from, $to)
            ->where('status', '!=', MembershipStatusEnum::Cancelled->value)
            ->with('membershipPlan')
            ->get()
            ->groupBy('membership_plan_id')
            ->map(fn ($terms) => [
                'plan' => $terms->first()->membershipPlan?->name ?? 'Unknown',
                'terms' => $terms->count(),
                'gross' => round((float) $terms->sum('price'), 2),
                'discount' => round((float) $terms->sum('discount_amount'), 2),
                'joining_fees' => round((float) $terms->sum('joining_fee'), 2),
                'net' => round((float) $terms->sum('payable_amount'), 2),
            ])
            ->sortByDesc('net')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'total' => round(array_sum(array_column($rows, 'net')), 2),
        ];
    }

    /**
     * Floor traffic: visits per day, how many distinct members, and the hour
     * the gym is busiest.
     *
     * @return array<string, mixed>
     */
    public function attendance(?string $from = null, ?string $to = null): array
    {
        $from ??= now()->subDays(29)->toDateString();
        $to ??= now()->toDateString();

        $checkIns = MemberCheckIn::query()
            ->whereDate('checked_in_at', '>=', $from)
            ->whereDate('checked_in_at', '<=', $to)
            ->with('member.party')
            ->get();

        $perDay = $checkIns
            ->groupBy(fn (MemberCheckIn $c): string => $c->checked_in_at->toDateString())
            ->map(fn ($visits, $date): array => [
                'date' => $date,
                'visits' => $visits->count(),
                'members' => $visits->pluck('member_id')->unique()->count(),
            ])
            ->sortBy('date')
            ->values()
            ->all();

        $byHour = $checkIns
            ->groupBy(fn (MemberCheckIn $c): int => (int) $c->checked_in_at->format('G'))
            ->map->count()
            ->sortDesc();

        return [
            'from' => $from,
            'to' => $to,
            'total_visits' => $checkIns->count(),
            'unique_members' => $checkIns->pluck('member_id')->unique()->count(),
            'busiest_hour' => $byHour->keys()->first(),
            'per_day' => $perDay,
            'most_frequent' => $checkIns
                ->groupBy('member_id')
                ->map(fn ($visits): array => [
                    'member' => $visits->first()->member?->party?->name,
                    'member_code' => $visits->first()->member?->member_code,
                    'visits' => $visits->count(),
                ])
                ->sortByDesc('visits')
                ->take(10)
                ->values()
                ->all(),
        ];
    }

    /**
     * Constrain a query to the reporting window. Defaults to the current month,
     * which is what the screens open on.
     */
    private function windowed($query, ?string $from, ?string $to, string $column = 'start_date')
    {
        $from ??= now()->startOfMonth()->toDateString();
        $to ??= now()->endOfMonth()->toDateString();

        return $query->whereDate($column, '>=', $from)->whereDate($column, '<=', $to);
    }
}
